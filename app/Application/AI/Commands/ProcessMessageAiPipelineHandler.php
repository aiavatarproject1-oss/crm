<?php

namespace App\Application\AI\Commands;

use App\Application\AI\Contracts\MessageAiPipelineInterface;
use App\Application\AI\Contracts\PromptBuilderInterface;
use App\Application\AI\DTO\AiPipelineResult;
use App\Application\AI\DTO\LlmRequest;
use App\Application\Context\BuildConversationContextCommand;
use App\Application\Context\BuildConversationContextHandler;
use App\Application\Contracts\AdminTaskRepositoryInterface;
use App\Application\Contracts\DomainEventPublisherInterface;
use App\Application\Contracts\MessageRepositoryInterface;
use App\Application\Exceptions\ApplicationException;
use App\Application\Quality\Contracts\QualityCheckerInterface;
use App\Application\Rules\EvaluateMessageRulesCommand;
use App\Application\Rules\EvaluateMessageRulesHandler;
use App\Domain\AI\ValueObjects\AiResponseType;
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
    public function __construct(private EvaluateMessageRulesHandler $rules, private BuildConversationContextHandler $context, private PromptBuilderInterface $prompt, private GenerateResponseHandler $generate, private QualityCheckerInterface $quality, private MessageRepositoryInterface $messages, private AdminTaskRepositoryInterface $adminTasks, private DomainEventPublisherInterface $events) {}

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

        $context = $this->context->handle(new BuildConversationContextCommand($command->message->tenantId, $command->message->influencerId, $command->user_id, $command->message->conversationId, query_text: $command->message->content->value));
        $prompt = $this->prompt->build($context, $command->metadata);
        $response = $this->generate->handle(new GenerateResponseCommand(LlmRequest::fromPromptPayload((string) $command->message->conversationId, $prompt)));
        try {
            $quality = $this->quality->evaluate($command->message->content->value, $response->content, $context);
        } catch (Throwable $exception) {
            throw new ApplicationException('Quality evaluation failed.', previous: $exception);
        }
        if (! $quality->approved) {
            $task = new AdminTask(
                new AdminTaskId(self::id()),
                $command->message->tenantId,
                $command->message->influencerId,
                $command->message->conversationId,
                $command->message->id(),
                $quality->reason,
                metadata: ['quality_score' => $quality->score, 'issues' => $quality->issues, ...$quality->metadata],
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

    public function process(Message $message, UserId $userId): void
    {
        $this->handle(new ProcessMessageAiPipelineCommand($message, $userId));
    }

    private static function id(): string
    {
        return bin2hex(random_bytes(16));
    }
}
