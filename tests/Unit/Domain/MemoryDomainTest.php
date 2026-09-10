<?php

namespace Tests\Unit\Domain;

use App\Application\Contracts\MemoryRepositoryInterface;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Memory\Entities\Memory;
use App\Domain\Memory\ValueObjects\MemoryId;
use App\Domain\Memory\ValueObjects\MemoryStatus;
use App\Domain\Memory\ValueObjects\MemoryType;
use App\Domain\Message\ValueObjects\MessageBatchId;
use App\Domain\Shared\Exceptions\DomainException;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Domain\User\ValueObjects\UserId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class MemoryDomainTest extends TestCase
{
    public function test_it_creates_a_valid_memory(): void
    {
        $createdAt = new DateTimeImmutable('2026-09-07T21:30:00+00:00');
        $memory = Memory::create(
            new MemoryId('memory-1'),
            new TenantId('tenant-1'),
            new InfluencerId('influencer-1'),
            new UserId('user-1'),
            new MemoryType(MemoryType::PREFERENCE),
            'Likes tea',
            0.92,
            0.8,
            new MessageBatchId('batch-1'),
            ['extractor' => 'future'],
            $createdAt,
        );

        self::assertSame('memory-1', (string) $memory->id());
        self::assertSame(MemoryType::PREFERENCE, $memory->type->value);
        self::assertSame('Likes tea', $memory->content);
        self::assertSame(0.92, $memory->confidenceScore);
        self::assertSame(0.8, $memory->importanceScore);
        self::assertTrue($memory->status()->isActive());
        self::assertSame('batch-1', (string) $memory->sourceMessageBatchId);
        self::assertSame($createdAt, $memory->createdAt);
        self::assertTrue($memory->belongsToScope(
            new TenantId('tenant-1'),
            new InfluencerId('influencer-1'),
            new UserId('user-1'),
        ));
    }

    public function test_it_rejects_empty_content(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Memory content cannot be empty.');

        Memory::create(
            new MemoryId('memory-1'),
            new TenantId('tenant-1'),
            new InfluencerId('influencer-1'),
            new UserId('user-1'),
            new MemoryType(MemoryType::FACT),
            '   ',
            0.5,
            0.5,
        );
    }

    public function test_tenant_isolation_in_repository_scope(): void
    {
        $repo = new InMemoryMemoryRepository;
        $tenantA = new TenantId('tenant-a');
        $tenantB = new TenantId('tenant-b');
        $influencer = new InfluencerId('influencer-1');
        $user = new UserId('user-1');

        $repo->save($this->memory('m-a', $tenantA, $influencer, $user, 'Secret A'));
        $repo->save($this->memory('m-b', $tenantB, $influencer, $user, 'Secret B'));

        $forA = $repo->findActiveForUser($tenantA, $influencer, $user);
        $forB = $repo->searchByScope($tenantB, $influencer, $user);

        self::assertCount(1, $forA);
        self::assertSame('Secret A', $forA[0]->content);
        self::assertNull($repo->findById($tenantA, $influencer, $user, new MemoryId('m-b')));
        self::assertCount(1, $forB);
        self::assertSame('Secret B', $forB[0]->content);
    }

    public function test_status_transitions_activate_and_archive(): void
    {
        $memory = $this->memory('m-1', new TenantId('tenant-1'), new InfluencerId('inf-1'), new UserId('user-1'), 'Note');
        self::assertTrue($memory->status()->isActive());

        $memory->archive();
        self::assertTrue($memory->status()->isArchived());

        $memory->activate();
        self::assertTrue($memory->status()->isActive());
    }

    public function test_superseded_memory_is_terminal_for_activate_and_archive(): void
    {
        $memory = $this->memory('m-old', new TenantId('tenant-1'), new InfluencerId('inf-1'), new UserId('user-1'), 'Old fact');
        $successor = new MemoryId('m-new');
        $memory->supersede($successor);

        self::assertTrue($memory->status()->isSuperseded());
        self::assertSame('m-new', (string) $memory->supersededById());
        self::assertSame('m-new', $memory->metadata['superseded_by']);

        try {
            $memory->activate();
            self::fail('Expected DomainException when activating superseded memory.');
        } catch (DomainException $exception) {
            self::assertSame('Superseded memory cannot be activated.', $exception->getMessage());
        }

        try {
            $memory->archive();
            self::fail('Expected DomainException when archiving superseded memory.');
        } catch (DomainException $exception) {
            self::assertSame('Superseded memory cannot be archived.', $exception->getMessage());
        }

        $repo = new InMemoryMemoryRepository;
        $tenant = new TenantId('tenant-1');
        $influencer = new InfluencerId('inf-1');
        $user = new UserId('user-1');
        $repo->save($memory);
        $repo->save($this->memory('m-active', $tenant, $influencer, $user, 'Still active'));

        $active = $repo->findActiveForUser($tenant, $influencer, $user);
        self::assertCount(1, $active);
        self::assertSame('m-active', (string) $active[0]->id());
        self::assertSame([], array_filter(
            $repo->searchByScope($tenant, $influencer, $user, MemoryStatus::superseded()),
            static fn (Memory $item): bool => (string) $item->id() === 'm-active',
        ));
    }

    public function test_supported_memory_types_include_event_and_goal(): void
    {
        foreach ([MemoryType::PROFILE, MemoryType::PREFERENCE, MemoryType::FACT, MemoryType::RELATIONSHIP, MemoryType::EVENT, MemoryType::GOAL] as $type) {
            $memory = Memory::create(
                new MemoryId('memory-'.$type),
                new TenantId('tenant-1'),
                new InfluencerId('influencer-1'),
                new UserId('user-1'),
                new MemoryType($type),
                "Content for {$type}",
                0.7,
                0.6,
            );
            self::assertSame($type, $memory->type->value);
        }
    }

    private function memory(
        string $id,
        TenantId $tenantId,
        InfluencerId $influencerId,
        UserId $userId,
        string $content,
    ): Memory {
        return Memory::create(
            new MemoryId($id),
            $tenantId,
            $influencerId,
            $userId,
            new MemoryType(MemoryType::FACT),
            $content,
            0.9,
            0.75,
        );
    }
}

final class InMemoryMemoryRepository implements MemoryRepositoryInterface
{
    /** @var array<string, Memory> */
    private array $items = [];

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

        usort($matches, static fn (Memory $left, Memory $right): int => $right->importanceScore <=> $left->importanceScore);

        return array_slice($matches, 0, $limit);
    }

    public function findImportantUserMemories(TenantId $tenantId, InfluencerId $influencerId, UserId $userId, int $limit): array
    {
        return $this->findActiveForUser($tenantId, $influencerId, $userId, $limit);
    }
}
