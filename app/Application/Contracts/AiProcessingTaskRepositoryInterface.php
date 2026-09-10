<?php

namespace App\Application\Contracts;

use App\Domain\AI\Entities\AiProcessingTask;
use App\Domain\AI\ValueObjects\AiProcessingTaskId;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Message\ValueObjects\MessageBatchId;
use App\Domain\Tenant\ValueObjects\TenantId;

interface AiProcessingTaskRepositoryInterface
{
    public function find(AiProcessingTaskId $taskId): ?AiProcessingTask;

    public function findByMessageBatch(
        TenantId $tenantId,
        InfluencerId $influencerId,
        MessageBatchId $messageBatchId,
    ): ?AiProcessingTask;

    public function save(AiProcessingTask $task): void;
}
