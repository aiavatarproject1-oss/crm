<?php

namespace App\Application\Rules;

use App\Application\Contracts\DomainEventPublisherInterface;
use App\Application\Contracts\RuleRepositoryInterface;
use App\Application\DTO\RuleEvaluationResult;
use App\Domain\Rule\Entities\Rule;
use App\Domain\Rule\Services\RuleMatcherRegistry;
use App\Domain\Rule\ValueObjects\RuleDecision;
use App\Domain\Shared\Events\RuleTriggered;

final readonly class EvaluateMessageRulesHandler
{
    public function __construct(private RuleRepositoryInterface $rules, private RuleMatcherRegistry $matchers, private DomainEventPublisherInterface $events) {}

    public function handle(EvaluateMessageRulesCommand $command): RuleEvaluationResult
    {
        $message = $command->message;
        $rules = array_filter(
            $this->rules->findEnabledRules($message->tenantId, $message->influencerId),
            fn (Rule $rule): bool => $rule->enabled
                && $rule->tenantId->equals($message->tenantId)
                && $rule->influencerId->equals($message->influencerId),
        );
        usort($rules, fn (Rule $left, Rule $right): int => $right->priority <=> $left->priority);

        foreach ($rules as $rule) {
            $reason = $this->matchers->match($rule, $message->content->value);
            if ($reason !== null) {
                $this->events->publish(new RuleTriggered($rule->id(), $message->id(), $rule->action, $reason));

                return new RuleEvaluationResult($rule->action, $reason, [
                    'id' => (string) $rule->id(),
                    'name' => $rule->name,
                    'type' => $rule->type->value,
                    'priority' => $rule->priority,
                    'version' => $rule->version,
                    'metadata' => $rule->metadata,
                ]);
            }
        }

        return new RuleEvaluationResult(RuleDecision::allowAi(), 'No enabled rule matched.', null);
    }
}
