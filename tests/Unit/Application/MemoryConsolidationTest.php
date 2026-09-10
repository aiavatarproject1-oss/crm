<?php

namespace Tests\Unit\Application;

use App\Application\Contracts\MemoryRepositoryInterface;
use App\Application\Memory\DTO\MemoryConsolidationDecision;
use App\Application\Memory\Policies\DeterministicMemorySimilarityPolicy;
use App\Application\Memory\Policies\ScopedMemoryConflictDetector;
use App\Application\Memory\Services\MemoryConsolidationService;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Memory\Entities\Memory;
use App\Domain\Memory\Entities\MemoryCandidate;
use App\Domain\Memory\ValueObjects\MemoryCandidateId;
use App\Domain\Memory\ValueObjects\MemoryId;
use App\Domain\Memory\ValueObjects\MemoryStatus;
use App\Domain\Memory\ValueObjects\MemoryType;
use App\Domain\Message\ValueObjects\MessageBatchId;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Domain\User\ValueObjects\UserId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class MemoryConsolidationTest extends TestCase
{
    private ConsolidationMemoryStore $memories;

    private MemoryConsolidationService $service;

    protected function setUp(): void
    {
        $this->memories = new ConsolidationMemoryStore;
        $this->service = new MemoryConsolidationService(
            $this->memories,
            new ScopedMemoryConflictDetector(new DeterministicMemorySimilarityPolicy),
        );
    }

    public function test_duplicate_memory_is_rejected(): void
    {
        $this->memories->save($this->memory('m-old', 'Likes tea', influencerId: 'inf-1'));
        $candidate = $this->candidate('Likes tea', influencerId: 'inf-1');

        $decision = $this->service->consolidate($candidate);

        self::assertSame(MemoryConsolidationDecision::REJECT_DUPLICATE, $decision->strategy);
        self::assertNull($decision->resultingMemory);
        self::assertSame('m-old', (string) $decision->existingMemory?->id());
        self::assertCount(1, $this->memories->items);
        self::assertTrue($candidate->status()->isRejected());
    }

    public function test_conflicting_memory_supersedes_old(): void
    {
        $this->memories->save($this->memory('m-old', 'Lives in Tehran', influencerId: 'inf-1'));
        $candidate = $this->candidate('Lives in London', influencerId: 'inf-1');

        $decision = $this->service->consolidate($candidate);

        self::assertSame(MemoryConsolidationDecision::SUPERSEDE_EXISTING, $decision->strategy);
        self::assertNotNull($decision->resultingMemory);
        self::assertTrue($decision->existingMemory?->status()->isSuperseded());
        self::assertSame((string) $decision->resultingMemory->id(), (string) $decision->existingMemory->supersededById());
        self::assertSame((string) $decision->existingMemory->id(), $decision->existingMemory->metadata['consolidation']['source_memory_id']);
        self::assertSame((string) $decision->resultingMemory->id(), $decision->existingMemory->metadata['consolidation']['target_memory_id']);
        self::assertTrue($decision->resultingMemory->status()->isActive());
        self::assertCount(2, $this->memories->items);
    }

    public function test_independent_memory_creates_new_record(): void
    {
        $this->memories->save($this->memory('m-old', 'Likes tea', influencerId: 'inf-1'));
        $candidate = $this->candidate('Has a dog', influencerId: 'inf-1');

        $decision = $this->service->consolidate($candidate);

        self::assertSame(MemoryConsolidationDecision::CREATE_NEW, $decision->strategy);
        self::assertNotNull($decision->resultingMemory);
        self::assertTrue($decision->resultingMemory->status()->isActive());
        self::assertCount(2, $this->memories->active());
        self::assertTrue($this->memories->items['m-old']->status()->isActive());
    }

    public function test_influencer_isolation(): void
    {
        $this->memories->save($this->memory('m-sofia', 'Lives in Tehran', influencerId: 'influencer-sofia'));
        $candidate = $this->candidate('Lives in Tehran', influencerId: 'influencer-alex');

        $decision = $this->service->consolidate($candidate);

        self::assertSame(MemoryConsolidationDecision::CREATE_NEW, $decision->strategy);
        self::assertCount(2, $this->memories->items);
        self::assertTrue($this->memories->items['m-sofia']->status()->isActive());
    }

    public function test_history_is_preserved_after_supersede(): void
    {
        $this->memories->save($this->memory('m-old', 'Prefers morning chats', influencerId: 'inf-1'));
        $decision = $this->service->consolidate($this->candidate('Prefers evening chats', influencerId: 'inf-1'));

        $old = $this->memories->items['m-old'];
        $new = $decision->resultingMemory;

        self::assertNotNull($new);
        self::assertTrue($old->status()->isSuperseded());
        self::assertArrayHasKey('m-old', $this->memories->items);
        self::assertSame(MemoryStatus::SUPERSEDED, $old->status()->value);
        self::assertSame('Prefers morning chats', $old->content);
        self::assertSame((string) $new->id(), $old->metadata['consolidation']['target_memory_id']);
        self::assertSame('m-old', $new->metadata['consolidation']['source_memory_id']);
        self::assertNotEmpty($old->metadata['consolidation']['reason']);
    }

    private function candidate(string $content, string $influencerId): MemoryCandidate
    {
        return MemoryCandidate::create(
            new MemoryCandidateId(bin2hex(random_bytes(6))),
            new TenantId('tenant-1'),
            new InfluencerId($influencerId),
            new UserId('user-1'),
            new MemoryType(MemoryType::FACT),
            $content,
            0.9,
            0.8,
            new MessageBatchId('batch-1'),
            [],
            new DateTimeImmutable('2026-09-07T22:40:00+00:00'),
        );
    }

    private function memory(string $id, string $content, string $influencerId): Memory
    {
        return Memory::create(
            new MemoryId($id),
            new TenantId('tenant-1'),
            new InfluencerId($influencerId),
            new UserId('user-1'),
            new MemoryType(MemoryType::FACT),
            $content,
            0.9,
            0.8,
            new MessageBatchId('batch-0'),
            [],
            new DateTimeImmutable('2026-09-07T20:00:00+00:00'),
        );
    }
}

final class ConsolidationMemoryStore implements MemoryRepositoryInterface
{
    /** @var array<string, Memory> */
    public array $items = [];

    public function save(Memory $memory): void
    {
        $this->items[(string) $memory->id()] = $memory;
    }

    public function findById(TenantId $tenantId, InfluencerId $influencerId, UserId $userId, MemoryId $memoryId): ?Memory
    {
        $memory = $this->items[(string) $memoryId] ?? null;
        if ($memory === null || ! $memory->belongsToScope($tenantId, $influencerId, $userId)) {
            return null;
        }

        return $memory;
    }

    public function findActiveForUser(TenantId $tenantId, InfluencerId $influencerId, UserId $userId, int $limit = 50): array
    {
        return $this->searchByScope($tenantId, $influencerId, $userId, MemoryStatus::active(), null, $limit);
    }

    public function findByType(TenantId $tenantId, InfluencerId $influencerId, UserId $userId, MemoryType $type): array
    {
        return $this->searchByScope($tenantId, $influencerId, $userId, null, $type);
    }

    public function searchByScope(
        TenantId $tenantId,
        InfluencerId $influencerId,
        UserId $userId,
        ?MemoryStatus $status = null,
        ?MemoryType $type = null,
        int $limit = 100,
    ): array {
        $matches = array_values(array_filter(
            $this->items,
            static function (Memory $memory) use ($tenantId, $influencerId, $userId, $status, $type): bool {
                if (! $memory->belongsToScope($tenantId, $influencerId, $userId)) {
                    return false;
                }
                if ($status !== null && $memory->status()->value !== $status->value) {
                    return false;
                }
                if ($type !== null && $memory->type->value !== $type->value) {
                    return false;
                }

                return true;
            },
        ));

        return array_slice($matches, 0, $limit);
    }

    public function findImportantUserMemories(TenantId $tenantId, InfluencerId $influencerId, UserId $userId, int $limit): array
    {
        return $this->findActiveForUser($tenantId, $influencerId, $userId, $limit);
    }

    /** @return list<Memory> */
    public function active(): array
    {
        return array_values(array_filter(
            $this->items,
            static fn (Memory $memory): bool => $memory->status()->isActive(),
        ));
    }
}
