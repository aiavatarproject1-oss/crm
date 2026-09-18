<?php

namespace App\Application\AI\Commands;

use App\Application\AI\Contracts\MessageAiPipelineInterface;
use App\Application\AI\Contracts\PromptBuilderInterface;
use App\Application\AI\Contracts\VisionAnalyzerInterface;
use App\Application\AI\DTO\AiPipelineResult;
use App\Application\AI\DTO\LlmRequest;
use App\Application\AI\DTO\LlmResponse;
use App\Application\AI\Services\EmojiPolicyEnforcer;
use App\Application\AI\Services\ImageMediaCollector;
use App\Application\AI\Services\VisionGroundednessGuard;
use App\Application\Context\BuildConversationContextCommand;
use App\Application\Context\BuildConversationContextHandler;
use App\Application\Contracts\AdminTaskRepositoryInterface;
use App\Application\Contracts\ConversationRepositoryInterface;
use App\Application\Contracts\DomainEventPublisherInterface;
use App\Application\Contracts\MessageRepositoryInterface;
use App\Application\Exceptions\ApplicationException;
use App\Application\Observability\PipelineMonitor;
use App\Application\Quality\Contracts\QualityCheckerInterface;
use App\Application\Quality\Contracts\QualityCheckRepositoryInterface;
use App\Application\Quality\DTO\QualityCheckRecord;
use App\Application\Quality\DTO\QualityResult;
use App\Application\Rules\EvaluateMessageRulesCommand;
use App\Application\Rules\EvaluateMessageRulesHandler;
use App\Domain\AI\ValueObjects\AiResponseType;
use App\Domain\Conversation\ValueObjects\ConversationStatus;
use App\Domain\Message\Entities\Message;
use App\Domain\Message\ValueObjects\ExternalMessageId;
use App\Domain\Message\ValueObjects\MessageContent;
use App\Domain\Message\ValueObjects\MessageId;
use App\Domain\Quality\Entities\AdminTask;
use App\Domain\Quality\ValueObjects\AdminTaskId;
use App\Domain\Quality\ValueObjects\ResponseDecision;
use App\Domain\Rule\ValueObjects\RuleDecision;
use App\Domain\User\ValueObjects\UserId;
use Throwable;

final readonly class ProcessMessageAiPipelineHandler implements MessageAiPipelineInterface
{
    public function __construct(
        private EvaluateMessageRulesHandler $rules,
        private BuildConversationContextHandler $context,
        private PromptBuilderInterface $prompt,
        private GenerateResponseHandler $generate,
        private QualityCheckerInterface $quality,
        private QualityCheckRepositoryInterface $qualityChecks,
        private MessageRepositoryInterface $messages,
        private AdminTaskRepositoryInterface $adminTasks,
        private DomainEventPublisherInterface $events,
        private ConversationRepositoryInterface $conversations,
        private ?VisionAnalyzerInterface $vision = null,
        private ?PipelineMonitor $pipeline = null,
        private VisionGroundednessGuard $visionGuard = new VisionGroundednessGuard,
    ) {}

    public function handle(ProcessMessageAiPipelineCommand $command): AiPipelineResult
    {
        $rule = $this->rules->handle(new EvaluateMessageRulesCommand($command->message));
        if ($rule->decision->value !== RuleDecision::ALLOW_AI) {
            $decision = $rule->decision->value === RuleDecision::ADMIN_REVIEW ? ResponseDecision::ADMIN_REVIEW : ResponseDecision::REJECTED;

            return new AiPipelineResult($rule, null, null, new ResponseDecision($decision));
        }

        $responseType = AiResponseType::CONVERSATION_TURN;
        if ($command->message->batchId !== null) {
            $existing = $this->messages->findByBatchResponse(
                $command->message->tenantId,
                $command->message->influencerId,
                $command->message->batchId,
                $responseType,
            );
            if ($existing !== null) {
                return new AiPipelineResult($rule, null, (string) $existing->id(), new ResponseDecision(ResponseDecision::APPROVED));
            }
        }

        $imageUrls = $this->resolveImageUrls($command);
        $visionUserText = trim((string) ($command->metadata['batch_user_text'] ?? ''));
        if ($visionUserText === '') {
            $visionUserText = $command->message->content->value;
        }

        $context = $this->context->handle(new BuildConversationContextCommand(
            $command->message->tenantId,
            $command->message->influencerId,
            $command->user_id,
            $command->message->conversationId,
            query_text: $visionUserText,
        ));

        $visionFlagOn = $context->character === null || ($context->character->featureFlags['vision'] ?? true);
        $expectsImage = $imageUrls !== []
            || $command->message->contentType === 'image'
            || (bool) ($command->metadata['expects_image'] ?? false);
        $visionEnabled = $this->vision !== null && $expectsImage && $visionFlagOn;

        $this->pipeline?->info('pipeline.turn.context', [
            'conversation_id' => (string) $command->message->conversationId,
            'batch_id' => $command->message->batchId !== null ? (string) $command->message->batchId : null,
            'trigger_text' => mb_substr($command->message->content->value, 0, 300),
            'batch_user_text' => mb_substr($visionUserText, 0, 300),
            'image_urls' => $imageUrls,
            'expects_image' => $expectsImage,
            'will_call_vision' => $visionEnabled,
            'character_id' => $context->character?->characterId,
            'character_version' => $context->character?->version,
        ]);

        // Photos in turn → vision is mandatory. Never continue to chat without a real analysis.
        if ($expectsImage) {
            $visionAnalysis = ($this->vision !== null && $imageUrls !== [] && $visionFlagOn)
                ? $this->vision->analyze($imageUrls, $visionUserText)
                : null;

            $this->pipeline?->info('pipeline.vision.result', [
                'vision_used' => is_string($visionAnalysis) && $visionAnalysis !== '',
                'vision_chars' => is_string($visionAnalysis) ? strlen($visionAnalysis) : 0,
                'vision_preview' => is_string($visionAnalysis) ? mb_substr($visionAnalysis, 0, 400) : null,
            ]);

            if (! is_string($visionAnalysis) || trim($visionAnalysis) === '') {
                return $this->handoffOnVisionFail($command, $context, $rule, $imageUrls);
            }
        } else {
            $visionAnalysis = null;
            $this->pipeline?->info('pipeline.vision.result', [
                'vision_used' => false,
                'vision_chars' => 0,
                'vision_preview' => null,
            ]);
        }

        $prompt = $this->prompt->build($context, [
            ...$command->metadata,
            'vision_analysis' => $visionAnalysis,
            'image_urls' => $imageUrls,
            'batch_user_text' => $visionUserText,
        ]);
        $response = $this->generate->handle(new GenerateResponseCommand(LlmRequest::fromPromptPayload((string) $command->message->conversationId, $prompt)));
        $filteredContent = (new EmojiPolicyEnforcer)->apply($response->content, $context->character);
        if ($filteredContent !== $response->content) {
            $response = new LlmResponse(
                $filteredContent,
                $response->model,
                $response->tokens_used,
                $response->latency_ms,
                [...$response->metadata, 'emoji_policy_applied' => true],
            );
        }

        $visionUsed = is_string($visionAnalysis) && trim($visionAnalysis) !== '';
        if ($visionUsed && ! $this->visionGuard->isGrounded($response->content, (string) $visionAnalysis)) {
            $this->pipeline?->warning('pipeline.chat.ungrounded', [
                'reply' => mb_substr($response->content, 0, 400),
                'vision_preview' => mb_substr((string) $visionAnalysis, 0, 400),
            ]);

            // One isolated regenerate (history already stripped in prompt builder).
            $retryPrompt = $this->prompt->build($context, [
                ...$command->metadata,
                'vision_analysis' => $visionAnalysis,
                'image_urls' => $imageUrls,
                'batch_user_text' => $visionUserText,
                'vision_retry' => true,
            ]);
            $retry = $this->generate->handle(new GenerateResponseCommand(LlmRequest::fromPromptPayload((string) $command->message->conversationId, $retryPrompt)));
            $retryContent = (new EmojiPolicyEnforcer)->apply($retry->content, $context->character);
            if ($this->visionGuard->isGrounded($retryContent, (string) $visionAnalysis)) {
                $response = new LlmResponse(
                    $retryContent,
                    $retry->model,
                    $retry->tokens_used,
                    $retry->latency_ms,
                    [...$retry->metadata, 'vision_used' => true, 'vision_regenerated' => true],
                );
            } else {
                // Hard fallback: never ship a stale jacket/profile description again.
                $forced = $this->visionGuard->forcedReplyFromVision((string) $visionAnalysis, $visionUserText);
                $forced = (new EmojiPolicyEnforcer)->apply($forced, $context->character);
                $response = new LlmResponse(
                    $forced,
                    'vision-forced',
                    0,
                    0,
                    ['vision_used' => true, 'vision_forced' => true],
                );
                $this->pipeline?->warning('pipeline.chat.vision_forced', [
                    'reply' => mb_substr($forced, 0, 400),
                ]);
            }
        }

        $this->pipeline?->info('pipeline.chat.reply', [
            'model' => $response->model,
            'reply' => mb_substr($response->content, 0, 500),
            'vision_used' => $visionUsed,
            'character_id' => $context->character?->characterId,
            'character_version' => $context->character?->version,
        ]);

        $response = new LlmResponse(
            $response->content,
            $response->model,
            $response->tokens_used,
            $response->latency_ms,
            [...$response->metadata, 'vision_used' => $visionUsed],
        );

        // Vision-grounded turns: skip slow LLM quality gate (it times out / retries and can
        // overwrite a correct Escalade reply with a stale jacket reply on the second pass).
        if ($visionUsed && $this->visionGuard->isGrounded($response->content, (string) $visionAnalysis)) {
            $quality = new QualityResult(
                true,
                1.0,
                [],
                'Auto-approved: vision-grounded photo reply.',
                ['vision_grounded_skip_quality' => true],
            );
        } else {
            try {
                $quality = $this->quality->evaluate($command->message->content->value, $response->content, $context);
                if ($context->character !== null && $quality->approved && $quality->score < $context->character->qualityScoreThreshold()) {
                    $quality = new QualityResult(
                        false,
                        $quality->score,
                        [...$quality->issues, 'below_character_threshold'],
                        $quality->reason !== '' ? $quality->reason : 'Score below character quality threshold.',
                        $quality->metadata,
                    );
                }
            } catch (Throwable $exception) {
                throw new ApplicationException('Quality evaluation failed.', previous: $exception);
            }
        }

        $threshold = $context->character?->qualityScoreThreshold() ?? 0.7;
        $this->qualityChecks->save(new QualityCheckRecord(
            id: self::id(),
            tenantId: (string) $command->message->tenantId,
            characterId: (string) $command->message->influencerId,
            conversationId: (string) $command->message->conversationId,
            userMessageId: (string) $command->message->id(),
            userMessage: $command->message->content->value,
            aiResponse: $response->content,
            score: $quality->score,
            threshold: $threshold,
            approved: $quality->approved,
            issues: $quality->issues,
            reason: $quality->reason,
            metadata: [
                ...$quality->metadata,
                'model' => $response->model,
                'character_version' => $context->character?->version,
                'sales_stage' => $context->sales_stage,
            ],
        ));

        if (! $quality->approved) {
            $task = new AdminTask(
                new AdminTaskId(self::id()),
                $command->message->tenantId,
                $command->message->influencerId,
                $command->message->conversationId,
                $command->message->id(),
                $quality->reason,
                metadata: [
                    'quality_score' => $quality->score,
                    'threshold' => $threshold,
                    'issues' => $quality->issues,
                    'user_message' => $command->message->content->value,
                    'ai_response' => $response->content,
                    ...$quality->metadata,
                ],
            );
            $this->adminTasks->save($task);

            return new AiPipelineResult($rule, $response, null, new ResponseDecision(ResponseDecision::ADMIN_REVIEW), $quality, (string) $task->id());
        }

        if ($command->message->batchId !== null) {
            $existing = $this->messages->findByBatchResponse(
                $command->message->tenantId,
                $command->message->influencerId,
                $command->message->batchId,
                $responseType,
            );
            if ($existing !== null) {
                return new AiPipelineResult($rule, $response, (string) $existing->id(), new ResponseDecision(ResponseDecision::APPROVED), $quality);
            }
        }

        $message = Message::create(
            new MessageId(self::id()),
            $command->message->tenantId,
            $command->message->influencerId,
            $command->message->conversationId,
            'ai',
            new MessageContent($response->content),
            new ExternalMessageId('ai-'.self::id()),
            $command->message->platform,
            null,
            'outgoing',
            'text',
            [
                'model' => $response->model,
                'tokens_used' => $response->tokens_used,
                'latency_ms' => $response->latency_ms,
                'response_type' => $responseType,
                'vision_used' => is_string($visionAnalysis) && $visionAnalysis !== '',
                'vision_preview' => is_string($visionAnalysis) ? mb_substr($visionAnalysis, 0, 180) : null,
                ...$response->metadata,
            ],
            $command->message->batchId,
        );
        $this->messages->save($message);
        foreach ($message->releaseDomainEvents() as $event) {
            $this->events->publish($event);
        }

        return new AiPipelineResult($rule, $response, (string) $message->id(), new ResponseDecision(ResponseDecision::APPROVED), $quality);
    }

    public function process(Message $message, UserId $userId, array $metadata = []): AiPipelineResult
    {
        return $this->handle(new ProcessMessageAiPipelineCommand($message, $userId, $metadata));
    }

    /**
     * Silent handoff: no user-facing reply. Pause character and notify support IDs via bot.
     */
    private function handoffOnVisionFail(
        ProcessMessageAiPipelineCommand $command,
        \App\Application\Context\DTO\ConversationContext $context,
        \App\Application\DTO\RuleEvaluationResult $rule,
        array $imageUrls,
    ): AiPipelineResult {
        $character = $context->character;
        $handoff = (array) ($character?->handoff ?? []);
        $featureFlags = (array) ($character?->featureFlags ?? []);
        $allowHandoff = $character === null || ($featureFlags['handoff'] ?? true);

        $supportIds = [];
        foreach ((array) ($handoff['support_telegram_ids'] ?? []) as $id) {
            $id = trim((string) $id);
            if ($id !== '') {
                $supportIds[] = $id;
            }
        }

        $panelBase = rtrim((string) config('app.admin_panel_url', 'http://localhost:3000'), '/');
        $panelUrl = $panelBase.'/fa/conversations/'.(string) $command->message->conversationId;
        if (! ($handoff['panel_deep_link'] ?? true)) {
            $panelUrl = null;
        }

        $this->pipeline?->error('pipeline.vision.handoff', [
            'conversation_id' => (string) $command->message->conversationId,
            'image_urls' => $imageUrls,
            'support_ids' => $supportIds,
            'allow_handoff' => $allowHandoff,
            'silent' => true,
        ]);

        if ($allowHandoff) {
            $conversation = $this->conversations->find($command->message->conversationId);
            if ($conversation !== null && ! $conversation->status()->isHandoff()) {
                $conversation->changeStatus(ConversationStatus::handoff());
                $this->conversations->save($conversation);
                foreach ($conversation->releaseDomainEvents() as $event) {
                    $this->events->publish($event);
                }
            }
        }

        $task = new AdminTask(
            new AdminTaskId(self::id()),
            $command->message->tenantId,
            $command->message->influencerId,
            $command->message->conversationId,
            $command->message->id(),
            'Vision failed — photo could not be analyzed. Character paused (silent handoff).',
            metadata: [
                'reason_code' => 'vision_fail',
                'image_urls' => $imageUrls,
                'support_telegram_ids' => $supportIds,
                'notify_mode' => (string) ($handoff['notify_mode'] ?? 'all'),
                'panel_url' => $panelUrl,
                'silent' => true,
            ],
        );
        $this->adminTasks->save($task);

        // Internal marker only — bot must NOT forward this text to the user.
        $message = Message::create(
            new MessageId(self::id()),
            $command->message->tenantId,
            $command->message->influencerId,
            $command->message->conversationId,
            'ai',
            new MessageContent('[handoff]'),
            new ExternalMessageId('ai-'.self::id()),
            $command->message->platform,
            null,
            'outgoing',
            'text',
            [
                'response_type' => AiResponseType::CONVERSATION_TURN,
                'vision_used' => false,
                'vision_fail' => true,
                'silent' => true,
                'handoff' => [
                    'reason' => 'vision_fail',
                    'notify' => $supportIds,
                    'notify_mode' => (string) ($handoff['notify_mode'] ?? 'all'),
                    'panel_url' => $panelUrl,
                    'admin_task_id' => (string) $task->id(),
                    'silent' => true,
                ],
            ],
            $command->message->batchId,
        );
        $this->messages->save($message);
        foreach ($message->releaseDomainEvents() as $event) {
            $this->events->publish($event);
        }

        return new AiPipelineResult(
            $rule,
            new LlmResponse('[handoff]', 'handoff', 0, 0, [
                'vision_fail' => true,
                'silent' => true,
                'notify' => $supportIds,
                'notify_mode' => (string) ($handoff['notify_mode'] ?? 'all'),
                'panel_url' => $panelUrl,
            ]),
            (string) $message->id(),
            new ResponseDecision(ResponseDecision::APPROVED),
            null,
            (string) $task->id(),
        );
    }

    /**
     * @return list<string>
     */
    private function resolveImageUrls(ProcessMessageAiPipelineCommand $command): array
    {
        $urls = [];
        $fromMeta = $command->metadata['image_urls'] ?? null;
        if (is_array($fromMeta)) {
            foreach ($fromMeta as $url) {
                if (is_string($url) && trim($url) !== '') {
                    $urls[] = trim($url);
                }
            }
        }

        foreach (ImageMediaCollector::fromMetadata($command->message->metadata) as $url) {
            $urls[] = $url;
        }

        return array_values(array_unique($urls));
    }

    private static function id(): string
    {
        return bin2hex(random_bytes(16));
    }
}
