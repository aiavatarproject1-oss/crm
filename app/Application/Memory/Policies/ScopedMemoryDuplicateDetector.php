<?php

namespace App\Application\Memory\Policies;

use App\Application\Contracts\MemoryRepositoryInterface;
use App\Application\Memory\Contracts\MemoryDuplicateDetectorInterface;
use App\Domain\Memory\Entities\MemoryCandidate;

/**
 * Scope-aware duplicate check against ACTIVE memories (same tenant/influencer/user + type + content).
 */
final readonly class ScopedMemoryDuplicateDetector implements MemoryDuplicateDetectorInterface
{
    public function __construct(private MemoryRepositoryInterface $memories) {}

    public function isDuplicate(MemoryCandidate $candidate): bool
    {
        $existing = $this->memories->findByType(
            $candidate->tenantId,
            $candidate->influencerId,
            $candidate->userId,
            $candidate->type,
        );

        $normalized = $this->normalize($candidate->content);
        if ($normalized === '') {
            return false;
        }

        foreach ($existing as $memory) {
            if (! $memory->status()->isActive()) {
                continue;
            }
            if ($this->normalize($memory->content) === $normalized) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $content): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $content) ?? $content));
    }
}
