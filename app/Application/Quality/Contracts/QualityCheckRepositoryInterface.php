<?php

namespace App\Application\Quality\Contracts;

use App\Application\Quality\DTO\QualityCheckRecord;

interface QualityCheckRepositoryInterface
{
    public function save(QualityCheckRecord $record): void;

    /**
     * @param  array{
     *   character_id?: string|null,
     *   approved?: bool|null,
     *   search?: string|null,
     * }  $filters
     * @return array{items: list<QualityCheckRecord>, total: int}
     */
    public function paginate(int $page, int $perPage, array $filters = []): array;
}
