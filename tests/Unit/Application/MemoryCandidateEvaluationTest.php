<?php

namespace Tests\Unit\Application;

use App\Application\Contracts\MemoryRepositoryInterface;
use App\Application\Memory\Contracts\MemoryDuplicateDetectorInterface;
use App\Application\Memory\DTO\MemoryEvaluationResult;
use App\Application\Memory\Policies\ScopedMemoryDuplicateDetector;
use App\Application\Memory\Policies\ThresholdMemoryEvaluationPolicy;
use App\Application\Memory\Services\MemoryEvaluator;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Memory\Entities\Memory;
use App\Domain\Memory\Entities\MemoryCandidate;
use App\Domain\Memory\ValueObjects\MemoryCandidateId;
use App\Domain\Memory\ValueObjects\MemoryCandidateStatus;
use App\Domain\Memory\ValueObjects\MemoryId;
use App\Domain\Memory\ValueObjects\MemoryStatus;
use App\Domain\Memory\ValueObjects\MemoryType;
use App\Domain\Message\ValueObjects\MessageBatchId;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Domain\User\ValueObjects\UserId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class MemoryCandidateEvaluationTest extends TestCase
{
    public function test_valid_candidate_is_approved(): void
    {
        $candidate = $this->candidate(content: 'Likes tea', confidence: 0.9, importance: 0.7);
        $result = $this->evaluator(new NeverDuplicateDetector)->evaluate($candidate);

        self::assertTrue($result->approved());
        self::assertSame(MemoryEvaluationResult::APPROVE, $result->decision);
        self::assertTrue($candidate->status()->isApproved());
        self::assertSame(MemoryCandidateStatus::APPROVED, $candidate->status()->value);
    }

    public function test_low_confidence_is_rejected(): void
    {
        $candidate = $this->candidate(content: 'Likes tea', confidence: 0.2, importance: 0.7);
        $result = $this->evaluator(new NeverDuplicateDetector)->evaluate($candidate);

        self::assertTrue($result->rejected());
        self::assertContains('low_confidence', $result->issues);
        self::assertTrue($candidate->status()->isRejected());
        self::assertNotNull($candidate->rejectionReason());
    }

    public function test_empty_content_is_rejected(): void
    {
        $candidate = $this->candidate(content: '   ', confidence: 0.95, importance: 0.8);
        $result = $this->evaluator(new NeverDuplicateDetector)->evaluate($candidate);

        self::assertTrue($result->rejected());
        self::assertContains('empty_content', $result->issues);
        self::assertTrue($candidate->status()->isRejected());
    }

    public function test_duplicate_candidate_is_rejected(): void
    {
        $candidate = $this->candidate(content: 'Lives in Tehran', confidence: 0.9, importance: 0.8);
        $result = $this->evaluator(new AlwaysDuplicateDetector)->evaluate($candidate);

        self::assertTrue($result->rejected());
        self::assertContains('duplicate', $result->issues);
        self::assertTrue($candidate->status()->isRejected());
    }

    public function test_duplicate_detection_is_tenant_isolated(): void
    {
        $memories = new EvaluationMemoryStore;
        $memories->save(Memory::create(
            new MemoryId('memory-other-tenant'),
            new TenantId('tenant-b'),
            new InfluencerId('influencer-1'),
            new UserId('user-1'),
            new MemoryType(MemoryType::FACT),
            'Lives in Tehran',
            0.9,
            0.8,
        ));

        $detector = new ScopedMemoryDuplicateDetector($memories);
        $candidate = $this->candidate(
            content: 'Lives in Tehran',
            confidence: 0.9,
            importance: 0.8,
            tenantId: 'tenant-a',
        );

        self::assertFalse($detector->isDuplicate($candidate));

        $result = $this->evaluator($detector)->evaluate($candidate);
        self::assertTrue($result->approved());
        self::assertTrue($candidate->belongsToScope(
            new TenantId('tenant-a'),
            new InfluencerId('influencer-1'),
            new UserId('user-1'),
        ));
        self::assertFalse($candidate->belongsToScope(
            new TenantId('tenant-b'),
            new InfluencerId('influencer-1'),
            new UserId('user-1'),
        ));
    }

    public function test_source_batch_is_tracked_on_candidate(): void
    {
        $batchId = new MessageBatchId('batch-turn-42');
        $candidate = $this->candidate(
            content: 'Prefers morning chats',
            confidence: 0.88,
            importance: 0.6,
            batchId: $batchId,
        );

        self::assertSame('batch-turn-42', (string) $candidate->sourceMessageBatchId);

        $result = $this->evaluator(new NeverDuplicateDetector)->evaluate($candidate);
        self::assertTrue($result->approved());
        self::assertSame('batch-turn-42', (string) $candidate->sourceMessageBatchId);
    }

    private function evaluator(MemoryDuplicateDetectorInterface $duplicates): MemoryEvaluator
    {
        return new MemoryEvaluator(new ThresholdMemoryEvaluationPolicy($duplicates));
    }

    private function candidate(
        string $content,
        float $confidence,
        float $importance,
        string $tenantId = 'tenant-a',
        ?MessageBatchId $batchId = null,
    ): MemoryCandidate {
        return MemoryCandidate::create(
            new MemoryCandidateId(bin2hex(random_bytes(8))),
            new TenantId($tenantId),
            new InfluencerId('influencer-1'),
            new UserId('user-1'),
            new MemoryType(MemoryType::PREFERENCE),
            $content,
            $confidence,
            $importance,
            $batchId ?? new MessageBatchId('batch-1'),
            ['fixture' => true],
            new DateTimeImmutable('2026-09-07T22:00:00+00:00'),
        );
    }
}

final class NeverDuplicateDetector implements MemoryDuplicateDetectorInterface
{
    public function isDuplicate(MemoryCandidate $candidate): bool
    {
        return false;
    }
}

final class AlwaysDuplicateDetector implements MemoryDuplicateDetectorInterface
{
    public function isDuplicate(MemoryCandidate $candidate): bool
    {
        return true;
    }
}

final class EvaluationMemoryStore implements MemoryRepositoryInterface
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

        return array_slice($matches, 0, $limit);
    }

    public function findImportantUserMemories(TenantId $tenantId, InfluencerId $influencerId, UserId $userId, int $limit): array
    {
        return $this->findActiveForUser($tenantId, $influencerId, $userId, $limit);
    }
}
