<?php

namespace App\Application\Commands\ReceiveIncomingMessageBatch;

use App\Application\AI\Contracts\AiProcessingDispatcherInterface;
use App\Application\Character\Contracts\CharacterSettingsRepositoryInterface;
use App\Application\Contracts\AiProcessingTaskRepositoryInterface;
use App\Application\Contracts\ConversationRepositoryInterface;
use App\Application\Contracts\DomainEventPublisherInterface;
use App\Application\Contracts\MessageBatchRepositoryInterface;
use App\Application\Contracts\MessageRepositoryInterface;
use App\Application\Contracts\UserRepositoryInterface;
use App\Application\DTO\IncomingBatchMessageItem;
use App\Application\DTO\MessageBatchIngestionResult;
use App\Application\Exceptions\ApplicationException;
use App\Application\Observability\Contracts\StructuredLoggerInterface;
use App\Domain\AI\Entities\AiProcessingTask;
use App\Domain\AI\ValueObjects\AiProcessingTaskId;
use App\Domain\Conversation\Entities\Conversation;
use App\Domain\Conversation\ValueObjects\ConversationId;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Message\Entities\Message;
use App\Domain\Message\Entities\MessageBatch;
use App\Domain\Message\ValueObjects\ExternalMessageId;
use App\Domain\Message\ValueObjects\MessageBatchId;
use App\Domain\Message\ValueObjects\MessageContent;
use App\Domain\Message\ValueObjects\MessageId;
use App\Domain\Message\ValueObjects\MessagePlatform;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Domain\User\Entities\User;
use App\Domain\User\ValueObjects\UserId;
use DateTimeImmutable;

final readonly class ReceiveIncomingMessageBatchHandler
{
    public function __construct(
        private UserRepositoryInterface $users,
        private ConversationRepositoryInterface $conversations,
        private MessageRepositoryInterface $messages,
        private MessageBatchRepositoryInterface $batches,
        private DomainEventPublisherInterface $events,
        private AiProcessingTaskRepositoryInterface $aiTasks,
        private AiProcessingDispatcherInterface $aiDispatcher,
        private ?CharacterSettingsRepositoryInterface $characters = null,
        private ?StructuredLoggerInterface $logger = null,
    ) {}

    public function handle(ReceiveIncomingMessageBatchCommand $command): MessageBatchIngestionResult
    {
        $data = $command->batch;
        if ($data->tenant_id === null || $data->influencer_id === null) {
            throw new ApplicationException('Tenant and influencer identifiers are required.');
        }
        if ($data->messages === []) {
            throw new ApplicationException('Message batch cannot be empty.');
        }

        $tenantId = new TenantId($data->tenant_id);
        $influencerId = new InfluencerId($data->influencer_id);
        $platform = new MessagePlatform($data->platform);

        $this->logger?->info('inbound.batch.started', [
            'tenant_id' => (string) $tenantId,
            'influencer_id' => (string) $influencerId,
            'platform' => $data->platform,
            'message_count' => count($data->messages),
        ]);

        $user = $this->resolveUser($tenantId, $influencerId, $data->platform, $data->external_user_id, $data->username);
        $activityAt = $this->latestReceivedAt($data->messages);
        $conversation = $this->resolveConversation($tenantId, $influencerId, $user->id(), $data->platform, $activityAt);

        $batchId = new MessageBatchId(self::id());
        $resolvedIds = [];
        $createdCount = 0;
        $duplicateCount = 0;

        foreach ($data->messages as $item) {
            $existing = $this->messages->findByExternalIdentity(
                $tenantId,
                $influencerId,
                $data->platform,
                $item->external_message_id,
            );

            if ($existing !== null) {
                $resolvedIds[] = $existing->id();
                $duplicateCount++;

                continue;
            }

            $metadata = [
                'adapter_version' => $data->adapter_version,
                ...$data->metadata,
                ...$item->metadata,
                'raw_payload' => $item->raw_payload,
            ];
            if ($item->media !== []) {
                $metadata['media'] = $item->media;
            }

            $contentType = $item->content_type !== '' ? $item->content_type : 'text';
            $text = trim($item->text) !== ''
                ? $item->text
                : ($contentType === 'image' || $item->media !== [] ? '[image]' : '[media]');

            $message = Message::create(
                new MessageId(self::id()),
                $tenantId,
                $influencerId,
                $conversation->id(),
                'user',
                new MessageContent($text),
                new ExternalMessageId($item->external_message_id),
                $platform,
                $item->received_at,
                'incoming',
                $contentType,
                $metadata,
                $batchId,
            );
            $this->messages->save($message);
            foreach ($message->releaseDomainEvents() as $event) {
                $this->events->publish($event);
            }

            $resolvedIds[] = $message->id();
            $createdCount++;
        }

        $messageIdStrings = array_map(static fn (MessageId $id): string => (string) $id, $resolvedIds);
        $allDuplicate = $createdCount === 0;

        if ($allDuplicate) {
            $this->logger?->info('inbound.batch.duplicate', [
                'tenant_id' => (string) $tenantId,
                'influencer_id' => (string) $influencerId,
                'conversation_id' => (string) $conversation->id(),
                'duplicate_count' => $duplicateCount,
            ]);

            $notify = $this->supportNotify((string) $tenantId, (string) $influencerId, (string) $conversation->id());

            return new MessageBatchIngestionResult(
                '',
                (string) $conversation->id(),
                (string) $user->id(),
                count($resolvedIds),
                0,
                $duplicateCount,
                $messageIdStrings,
                false,
                true,
                '',
                $conversation->status()->blocksAi(),
                $conversation->status()->blocksAi(),
                $notify['notify'],
                $notify['notify_mode'],
                $notify['panel_url'],
            );
        }

        $batch = MessageBatch::create(
            $batchId,
            $tenantId,
            $influencerId,
            $user->id(),
            $conversation->id(),
            $platform,
            $resolvedIds,
            $activityAt,
            $activityAt,
            [
                'adapter_version' => $data->adapter_version,
                ...$data->metadata,
                'created_count' => $createdCount,
                'duplicate_count' => $duplicateCount,
            ],
        );
        $this->batches->save($batch);

        $inHandoff = $conversation->status()->blocksAi();
        $taskId = '';

        if ($inHandoff) {
            $this->logger?->info('inbound.batch.ai_skipped_handoff', [
                'tenant_id' => (string) $tenantId,
                'influencer_id' => (string) $influencerId,
                'conversation_id' => (string) $conversation->id(),
                'batch_id' => (string) $batchId,
                'status' => $conversation->status()->value,
            ]);
        } else {
            $existingTask = $this->aiTasks->findByMessageBatch($tenantId, $influencerId, $batchId);
            if ($existingTask === null) {
                $task = AiProcessingTask::create(
                    new AiProcessingTaskId(self::id()),
                    $tenantId,
                    $influencerId,
                    $conversation->id(),
                    $batchId,
                    [
                        'user_id' => (string) $user->id(),
                        'trigger_message_id' => (string) $resolvedIds[array_key_last($resolvedIds)],
                    ],
                );
                $this->aiTasks->save($task);
                $this->aiDispatcher->dispatch($task);
                $taskId = (string) $task->id();
                $this->logger?->info('inbound.batch.task_dispatched', [
                    'tenant_id' => (string) $tenantId,
                    'influencer_id' => (string) $influencerId,
                    'conversation_id' => (string) $conversation->id(),
                    'batch_id' => (string) $batchId,
                    'task_id' => $taskId,
                ]);
            } else {
                $taskId = (string) $existingTask->id();
            }
        }

        $this->logger?->info('inbound.batch.completed', [
            'tenant_id' => (string) $tenantId,
            'influencer_id' => (string) $influencerId,
            'conversation_id' => (string) $conversation->id(),
            'batch_id' => (string) $batchId,
            'created_count' => $createdCount,
            'duplicate_count' => $duplicateCount,
        ]);

        $notify = $this->supportNotify((string) $tenantId, (string) $influencerId, (string) $conversation->id());

        return new MessageBatchIngestionResult(
            (string) $batch->id(),
            (string) $conversation->id(),
            (string) $user->id(),
            count($resolvedIds),
            $createdCount,
            $duplicateCount,
            $messageIdStrings,
            true,
            false,
            $taskId,
            $inHandoff,
            $inHandoff,
            $notify['notify'],
            $notify['notify_mode'],
            $notify['panel_url'],
        );
    }

    private function resolveUser(
        TenantId $tenantId,
        InfluencerId $influencerId,
        string $platform,
        string $externalUserId,
        ?string $username,
    ): User {
        $user = $this->users->findByPlatformIdentity($tenantId, $platform, $externalUserId);
        if ($user === null) {
            $user = new User(new UserId(self::id()), $tenantId, $influencerId, $platform, $externalUserId, $username, null);
            $this->users->save($user);
            $user = $this->users->findByPlatformIdentity($tenantId, $platform, $externalUserId) ?? $user;
        }

        return $user;
    }

    private function resolveConversation(
        TenantId $tenantId,
        InfluencerId $influencerId,
        UserId $userId,
        string $platform,
        DateTimeImmutable $activityAt,
    ): Conversation {
        $conversation = $this->conversations->findActive($tenantId, $influencerId, $userId, $platform);
        if ($conversation === null) {
            $conversation = Conversation::start(new ConversationId(self::id()), $tenantId, $influencerId, $userId, $platform, $activityAt);
        } else {
            $conversation->recordActivity($activityAt);
        }
        $this->conversations->save($conversation);
        $conversation->releaseDomainEvents();

        return $conversation;
    }

    /**
     * @param  list<IncomingBatchMessageItem>  $messages
     */
    private function latestReceivedAt(array $messages): DateTimeImmutable
    {
        $latest = $messages[0]->received_at;
        foreach ($messages as $item) {
            if ($item->received_at > $latest) {
                $latest = $item->received_at;
            }
        }

        return $latest;
    }

    /**
     * @return array{notify: list<string>, notify_mode: string, panel_url: ?string}
     */
    private function supportNotify(string $tenantId, string $characterId, string $conversationId): array
    {
        $character = $this->characters?->findByCharacter($tenantId, $characterId);
        $handoff = (array) ($character?->handoff ?? []);
        $notify = [];
        foreach ((array) ($handoff['support_telegram_ids'] ?? []) as $id) {
            $id = trim((string) $id);
            if ($id !== '') {
                $notify[] = $id;
            }
        }

        $panelUrl = null;
        if ($handoff['panel_deep_link'] ?? true) {
            $panelBase = rtrim((string) config('app.admin_panel_url', 'http://localhost:3000'), '/');
            $panelUrl = $panelBase.'/fa/conversations/'.$conversationId;
        }

        return [
            'notify' => array_values(array_unique($notify)),
            'notify_mode' => (string) ($handoff['notify_mode'] ?? 'all'),
            'panel_url' => $panelUrl,
        ];
    }

    private static function id(): string
    {
        return bin2hex(random_bytes(16));
    }
}
