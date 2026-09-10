<?php

namespace App\Domain\Quality\Entities;

use App\Domain\Conversation\ValueObjects\ConversationId;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Quality\ValueObjects\QualityEvaluationId;
use App\Domain\Shared\Contracts\Entity;
use App\Domain\Tenant\ValueObjects\TenantId;

final readonly class QualityEvaluation implements Entity
{
    public function __construct(
        private QualityEvaluationId $qualityEvaluationId,
        public TenantId $tenantId,
        public InfluencerId $influencerId,
        public ConversationId $conversationId,
        public float $score,
        public bool $approved,
        public array $issues,
        public array $suggestions,
    ) {}

    public function id(): QualityEvaluationId
    {
        return $this->qualityEvaluationId;
    }
}
