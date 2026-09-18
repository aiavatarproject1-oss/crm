<?php

namespace App\Interfaces\Http\Controllers;

use App\Application\Commands\ReceiveIncomingMessageBatch\ReceiveIncomingMessageBatchCommand;
use App\Application\Commands\ReceiveIncomingMessageBatch\ReceiveIncomingMessageBatchHandler;
use App\Application\DTO\IncomingBatchMessageItem;
use App\Application\DTO\IncomingMessageBatchData;
use App\Application\Observability\Contracts\StructuredLoggerInterface;
use App\Application\Observability\PipelineMonitor;
use App\Infrastructure\Messaging\PlatformResolver;
use App\Interfaces\Http\Requests\InboundMessageRequest;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final readonly class InboundMessageController
{
    public function __construct(
        private PlatformResolver $resolver,
        private ReceiveIncomingMessageBatchHandler $handler,
        private ?StructuredLoggerInterface $logger = null,
        private ?PipelineMonitor $pipeline = null,
    ) {}

    public function store(InboundMessageRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $summary = PipelineMonitor::summarizeInbound($validated);
        $this->logger?->info('inbound.request.received', [
            'platform' => $validated['platform'] ?? null,
            'tenant_id' => $validated['tenant_id'] ?? null,
            'influencer_id' => $validated['influencer_id'] ?? null,
            'has_messages_array' => isset($validated['messages']),
            'image_message_count' => $summary['image_message_count'] ?? 0,
        ]);
        $this->pipeline?->info('inbound.request.received', $summary);

        $batch = isset($validated['messages'])
            ? $this->batchFromMessages($validated)
            : $this->batchFromPlatformPayload($validated);

        $result = $this->handler->handle(new ReceiveIncomingMessageBatchCommand($batch));

        $this->logger?->info('inbound.request.completed', [
            'tenant_id' => $validated['tenant_id'] ?? null,
            'influencer_id' => $validated['influencer_id'] ?? null,
            'created' => $result->created,
            'duplicate' => $result->duplicate,
            'created_count' => $result->created_count,
            'status' => $result->created ? 201 : 200,
        ]);
        $this->pipeline?->info('inbound.request.completed', [
            'tenant_id' => $validated['tenant_id'] ?? null,
            'influencer_id' => $validated['influencer_id'] ?? null,
            'platform' => $validated['platform'] ?? null,
            'created' => $result->created,
            'duplicate' => $result->duplicate,
            'created_count' => $result->created_count,
            'task_id' => $result->task_id,
            'conversation_id' => $result->conversation_id,
            'batch_id' => $result->batch_id,
            'user_id' => $result->user_id,
            'status' => $result->created ? 201 : 200,
        ]);

        return response()->json(
            ['success' => true, 'data' => $result->toArray()],
            $result->created ? 201 : 200,
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function batchFromMessages(array $validated): IncomingMessageBatchData
    {
        $messages = [];
        foreach ($validated['messages'] as $item) {
            $receivedAt = isset($item['received_at'])
                ? new DateTimeImmutable((string) $item['received_at'])
                : new DateTimeImmutable;

            $media = array_values(array_map(
                static function (array $entry): array {
                    $normalized = ['url' => (string) $entry['url']];
                    if (isset($entry['type'])) {
                        $normalized['type'] = (string) $entry['type'];
                    }
                    if (isset($entry['mime_type'])) {
                        $normalized['mime_type'] = (string) $entry['mime_type'];
                    }

                    return $normalized;
                },
                array_filter((array) ($item['media'] ?? []), static fn ($entry): bool => is_array($entry) && isset($entry['url'])),
            ));

            $contentType = (string) ($item['content_type'] ?? ($media !== [] ? 'image' : 'text'));
            $text = (string) ($item['text'] ?? '');
            if (trim($text) === '') {
                $text = $contentType === 'image' || $media !== [] ? '[image]' : '[media]';
            }

            $messages[] = new IncomingBatchMessageItem(
                (string) $item['external_message_id'],
                $text,
                $receivedAt,
                (array) ($item['metadata'] ?? []),
                (array) ($item['raw_payload'] ?? []),
                $contentType,
                $media,
            );
        }

        return (new IncomingMessageBatchData(
            (string) $validated['platform'],
            (string) $validated['external_user_id'],
            isset($validated['username']) ? (string) $validated['username'] : null,
            (string) $validated['tenant_id'],
            (string) $validated['influencer_id'],
            $messages,
            (array) ($validated['metadata'] ?? []),
        ))->withOwnership((string) $validated['tenant_id'], (string) $validated['influencer_id']);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function batchFromPlatformPayload(array $validated): IncomingMessageBatchData
    {
        try {
            $adapter = $this->resolver->resolve($validated['platform']);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['platform' => ['The selected platform is not supported.']]);
        }

        $message = $adapter->normalize($validated['payload'])
            ->withOwnership($validated['tenant_id'], $validated['influencer_id']);

        return IncomingMessageBatchData::fromPlatformMessage($message);
    }
}
