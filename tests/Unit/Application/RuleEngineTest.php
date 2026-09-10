<?php

namespace Tests\Unit\Application;

use App\Application\Contracts\DomainEventPublisherInterface;
use App\Application\Contracts\RuleRepositoryInterface;
use App\Application\Rules\EvaluateMessageRulesCommand;
use App\Application\Rules\EvaluateMessageRulesHandler;
use App\Domain\Conversation\ValueObjects\ConversationId;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Message\Entities\Message;
use App\Domain\Message\ValueObjects\ExternalMessageId;
use App\Domain\Message\ValueObjects\MessageContent;
use App\Domain\Message\ValueObjects\MessageId;
use App\Domain\Message\ValueObjects\MessagePlatform;
use App\Domain\Rule\Entities\Rule;
use App\Domain\Rule\Services\KeywordRuleMatcher;
use App\Domain\Rule\Services\RegexRuleMatcher;
use App\Domain\Rule\Services\RuleMatcherRegistry;
use App\Domain\Rule\ValueObjects\RuleDecision;
use App\Domain\Rule\ValueObjects\RuleId;
use App\Domain\Rule\ValueObjects\RuleType;
use App\Domain\Shared\Events\RuleTriggered;
use App\Domain\Tenant\ValueObjects\TenantId;
use PHPUnit\Framework\TestCase;

final class RuleEngineTest extends TestCase
{
    public function test_ai_identity_question_triggers_admin_review(): void
    {
        [$result, $events] = $this->evaluate('Are you an AI?', [$this->rule('identity', ['AI'], 10, RuleDecision::ADMIN_REVIEW)]);
        self::assertSame(RuleDecision::ADMIN_REVIEW, $result->decision->value);
        self::assertSame('identity', $result->matched_rule['name']);
        self::assertSame(1, $result->matched_rule['version']);
        self::assertCount(1, $events->items);
        self::assertInstanceOf(RuleTriggered::class, $events->items[0]);
    }

    public function test_normal_conversation_allows_ai(): void
    {
        [$result] = $this->evaluate('Hello Sofia', [$this->rule('identity', ['AI'], 10, RuleDecision::ADMIN_REVIEW)]);
        self::assertSame(RuleDecision::ALLOW_AI, $result->decision->value);
        self::assertNull($result->matched_rule);
    }

    public function test_disabled_rule_is_ignored(): void
    {
        [$result, $events] = $this->evaluate('Are you an AI?', [$this->rule('identity', ['AI'], 10, RuleDecision::BLOCK, false)]);
        self::assertSame(RuleDecision::ALLOW_AI, $result->decision->value);
        self::assertCount(0, $events->items);
    }

    public function test_rules_from_another_tenant_are_ignored(): void
    {
        $foreign = $this->rule('foreign', ['AI'], 100, RuleDecision::BLOCK, true, 'tenant-2');
        [$result] = $this->evaluate('Are you an AI?', [$foreign]);
        self::assertSame(RuleDecision::ALLOW_AI, $result->decision->value);
    }

    public function test_highest_priority_matching_rule_wins(): void
    {
        $low = $this->rule('review', ['AI'], 10, RuleDecision::ADMIN_REVIEW);
        $high = $this->rule('block', ['/AI/i'], 100, RuleDecision::BLOCK, true, 'tenant-1', RuleType::REGEX);
        [$result] = $this->evaluate('Are you an AI?', [$low, $high]);
        self::assertSame(RuleDecision::BLOCK, $result->decision->value);
        self::assertSame('block', $result->matched_rule['name']);
        self::assertSame(100, $result->matched_rule['priority']);
    }

    private function evaluate(string $content, array $rules): array
    {
        $repository = new StubRuleRepository($rules);
        $events = new RuleEvents;
        $handler = new EvaluateMessageRulesHandler($repository, new RuleMatcherRegistry([new KeywordRuleMatcher, new RegexRuleMatcher]), $events);
        $message = Message::create(new MessageId('message-1'), new TenantId('tenant-1'), new InfluencerId('influencer-1'), new ConversationId('conversation-1'), 'user', new MessageContent($content), new ExternalMessageId('external-1'), new MessagePlatform('telegram'));

        return [$handler->handle(new EvaluateMessageRulesCommand($message)), $events];
    }

    private function rule(string $name, array $patterns, int $priority, string $decision, bool $enabled = true, string $tenant = 'tenant-1', string $type = RuleType::KEYWORD): Rule
    {
        return new Rule(new RuleId('rule-'.$name), new TenantId($tenant), new InfluencerId('influencer-1'), $name, new RuleType($type), $patterns, $priority, new RuleDecision($decision), $enabled, 1, ['source' => 'test']);
    }
}

final readonly class StubRuleRepository implements RuleRepositoryInterface
{
    public function __construct(private array $rules) {}

    public function findEnabledRules(TenantId $tenantId, InfluencerId $influencerId): array
    {
        return $this->rules;
    }

    public function save(Rule $rule): void {}
}

final class RuleEvents implements DomainEventPublisherInterface
{
    public array $items = [];

    public function publish(object $event): void
    {
        $this->items[] = $event;
    }
}
