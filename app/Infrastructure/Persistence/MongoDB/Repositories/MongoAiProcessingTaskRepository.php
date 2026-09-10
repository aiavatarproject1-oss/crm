<?php

namespace App\Infrastructure\Persistence\MongoDB\Repositories;

use App\Application\Contracts\AiProcessingTaskRepositoryInterface;
use App\Domain\AI\Entities\AiProcessingTask;
use App\Domain\AI\ValueObjects\AiProcessingTaskId;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Message\ValueObjects\MessageBatchId;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Infrastructure\Persistence\MongoDB\Documents\AiProcessingTaskDocument;
use App\Infrastructure\Persistence\MongoDB\Mappers\AiProcessingTaskMapper;
use App\Infrastructure\Persistence\MongoDB\Support\ExplicitIdPersister;
use MongoDB\Collection;

final class MongoAiProcessingTaskRepository implements AiProcessingTaskRepositoryInterface
{
    private bool $indexesEnsured = false;

    public function __construct(private readonly AiProcessingTaskMapper $mapper) {}

    public function find(AiProcessingTaskId $taskId): ?AiProcessingTask
    {
        $document = AiProcessingTaskDocument::query()->find((string) $taskId);

        return $document === null ? null : $this->mapper->toDomain($document);
    }

    public function findByMessageBatch(
        TenantId $tenantId,
        InfluencerId $influencerId,
        MessageBatchId $messageBatchId,
    ): ?AiProcessingTask {
        $document = AiProcessingTaskDocument::query()
            ->where('tenant_id', (string) $tenantId)
            ->where('influencer_id', (string) $influencerId)
            ->where('message_batch_id', (string) $messageBatchId)
            ->first();

        return $document === null ? null : $this->mapper->toDomain($document);
    }

    public function save(AiProcessingTask $task): void
    {
        $this->ensureIndexes();
        ExplicitIdPersister::save(
            $this->mapper->toDocument($task),
            (string) $task->id(),
            fn () => AiProcessingTaskDocument::query()
                ->where('tenant_id', (string) $task->tenantId)
                ->where('influencer_id', (string) $task->influencerId)
                ->where('message_batch_id', (string) $task->messageBatchId)
                ->first(),
        );
    }

    private function ensureIndexes(): void
    {
        if ($this->indexesEnsured) {
            return;
        }

        AiProcessingTaskDocument::raw(static function (Collection $collection): void {
            $collection->createIndexes([
                [
                    'key' => [
                        'tenant_id' => 1,
                        'influencer_id' => 1,
                        'message_batch_id' => 1,
                    ],
                    'name' => 'ai_processing_tasks_batch_unique',
                    'unique' => true,
                ],
                [
                    'key' => [
                        'tenant_id' => 1,
                        'influencer_id' => 1,
                        'status' => 1,
                        'started_at' => -1,
                    ],
                    'name' => 'ai_processing_tasks_status',
                ],
            ]);
        });
        $this->indexesEnsured = true;
    }
}
