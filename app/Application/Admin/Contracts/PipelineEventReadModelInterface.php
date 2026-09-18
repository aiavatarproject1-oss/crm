<?php

namespace App\Application\Admin\Contracts;

interface PipelineEventReadModelInterface
{
    /**
     * @param  array{level?: ?string, event?: ?string, conversation_id?: ?string, correlation_id?: ?string, search?: ?string, from?: ?string, to?: ?string}  $filters
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function paginate(int $page, int $perPage, array $filters = []): array;

    /**
     * @return list<string>
     */
    public function eventNames(): array;
}
