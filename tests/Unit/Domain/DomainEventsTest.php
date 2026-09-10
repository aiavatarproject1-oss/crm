<?php

namespace Tests\Unit\Domain;

use App\Domain\Conversation\ValueObjects\ConversationId;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Memory\ValueObjects\MemoryId;
use App\Domain\Message\ValueObjects\MessageId;
use App\Domain\Rule\ValueObjects\RuleDecision;
use App\Domain\Rule\ValueObjects\RuleId;
use App\Domain\Shared\Events\MemoryCreated;
use App\Domain\Shared\Events\QualityCheckRequested;
use App\Domain\Shared\Events\RuleTriggered;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Domain\User\ValueObjects\UserId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class DomainEventsTest extends TestCase
{
    public function test_supporting_domain_events_retain_their_context_identifiers(): void
    {
        $tenantId = new TenantId('tenant-1');
        $influencerId = new InfluencerId('influencer-1');
        $conversationId = new ConversationId('conversation-1');
        $occurredAt = new DateTimeImmutable('2026-09-03T14:00:00+00:00');

        $rule = new RuleTriggered(new RuleId('rule-1'), new MessageId('message-1'), new RuleDecision(RuleDecision::ADMIN_REVIEW), 'Matched identity keyword.');
        $memory = new MemoryCreated(new MemoryId('memory-1'), $tenantId, $influencerId, new UserId('user-1'), $occurredAt);
        $quality = new QualityCheckRequested(new MessageId('message-1'), $tenantId, $influencerId, $conversationId, $occurredAt);

        $this->assertSame('rule-1', $rule->ruleId->value);
        $this->assertSame('message-1', $rule->messageId->value);
        $this->assertSame(RuleDecision::ADMIN_REVIEW, $rule->decision->value);
        $this->assertSame('memory-1', $memory->memoryId->value);
        $this->assertSame('user-1', $memory->userId->value);
        $this->assertSame('message-1', $quality->messageId->value);
        $this->assertSame($occurredAt, $quality->occurredAt);
    }
}
