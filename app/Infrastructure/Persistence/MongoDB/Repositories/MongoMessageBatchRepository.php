<?php

namespace App\Infrastructure\Persistence\MongoDB\Repositories;

use App\Application\Contracts\MessageBatchRepositoryInterface;
use App\Domain\Message\Entities\MessageBatch;
use App\Domain\Message\ValueObjects\MessageBatchId;
use App\Infrastructure\Persistence\MongoDB\Documents\MessageBatchDocument;
use App\Infrastructure\Persistence\MongoDB\Mappers\MessageBatchMapper;
use App\Infrastructure\Persistence\MongoDB\Support\ExplicitIdPersister;

final class MongoMessageBatchRepository implements MessageBatchRepositoryInterface
{
    public function __construct(private readonly MessageBatchMapper $mapper) {}

    public function find(MessageBatchId $batchId): ?MessageBatch
    {
        $document = MessageBatchDocument::query()->find((string) $batchId);

        return $document === null ? null : $this->mapper->toDomain($document);
    }

    public function save(MessageBatch $batch): void
    {
        ExplicitIdPersister::save(
            $this->mapper->toDocument($batch),
            (string) $batch->id(),
        );
    }
}
