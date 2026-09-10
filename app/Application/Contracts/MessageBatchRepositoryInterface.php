<?php

namespace App\Application\Contracts;

use App\Domain\Message\Entities\MessageBatch;
use App\Domain\Message\ValueObjects\MessageBatchId;
use App\Domain\Shared\Contracts\RepositoryInterface;

interface MessageBatchRepositoryInterface extends RepositoryInterface
{
    public function find(MessageBatchId $batchId): ?MessageBatch;

    public function save(MessageBatch $batch): void;
}
