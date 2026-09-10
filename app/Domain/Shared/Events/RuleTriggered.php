<?php

namespace App\Domain\Shared\Events;

use App\Domain\Message\ValueObjects\MessageId;
use App\Domain\Rule\ValueObjects\RuleDecision;
use App\Domain\Rule\ValueObjects\RuleId;

final readonly class RuleTriggered
{
    public function __construct(
        public RuleId $ruleId,
        public MessageId $messageId,
        public RuleDecision $decision,
        public string $reason,
    ) {}
}
