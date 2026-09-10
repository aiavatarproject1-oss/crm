<?php

namespace App\Infrastructure\Persistence\MongoDB\Repositories;

use App\Application\Contracts\MessageBatchRepositoryInterface;
use App\Domain\Message\Entities\MessageBatch;
use App\Domain\Message\ValueObjects\MessageBatchId;
use App\Infrastructure\Persistence\MongoDB\Documents\MessageBatchDocument;
use App\Infrastructure\Persistence\MongoDB\Mappers\MessageBatchMapper;
use App\Infrastructure\Persistence\MongoDB\Support\ExplicitIdPersister;
use MongoDB\Collection;

final class MongoMessageBatchRepository implements MessageBatchRepositoryInterface
{
    private bool $indexesEnsured = false;

    public function __construct(private readonly MessageBatchMapper $mapper) {}

    public function find(MessageBatchId $batchId): ?MessageBatch
    {
        $document = MessageBatchDocument::query()->find((string) $batchId);

        return $document === null ? null : $this->mapper->toDomain($document);
    }

    public function save(MessageBatch $batch): void
    {
        $this->ensureIndexes();
        ExplicitIdPersister::save(
            $this->mapper->toDocument($batch),
            (string) $batch->id(),
        );
    }

    private function ensureIndexes(): void
    {
        if ($this->indexesEnsured) {
            return;
        }

        MessageBatchDocument::raw(static function (Collection $collection): void {
            $collection->createIndexes([
                [
                    'key' => [
                        'tenant_id' => 1,
                        'influencer_id' => 1,
                        'conversation_id' => 1,
                        'created_at' => -1,
                    ],
                    'name' => 'message_batches_conversation_created',
                ],
                [
                    'key' => [
                        'tenant_id' => 1,
                        'user_id' => 1,
                        'created_at' => -1,
                    ],
                    'name' => 'message_batches_user_created',
                ],
            ]);
        });
        $this->indexesEnsured = true;
    }
}
