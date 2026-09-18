<?php

namespace App\Application\Admin\Contracts;

/**
 * Read-only, denormalized queries for the admin monitor (never used by the AI pipeline).
 */
interface ConversationReadModelInterface
{
    /**
     * @param  array{tenant_id?: ?string, influencer_id?: ?string, status?: ?string, platform?: ?string, search?: ?string, active_within_minutes?: ?int}  $filters
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function paginate(int $page, int $perPage, array $filters = []): array;

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $conversationId): ?array;

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function messages(string $conversationId, int $page, int $perPage): array;

    /**
     * @return array<string, int>
     */
    public function dashboardStats(): array;
}
