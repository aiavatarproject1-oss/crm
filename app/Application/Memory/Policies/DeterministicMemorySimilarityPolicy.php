<?php

namespace App\Application\Memory\Policies;

use App\Application\Memory\Contracts\MemorySimilarityPolicyInterface;
use App\Application\Memory\DTO\MemoryRelation;

/**
 * Token Jaccard + shared subject prefix — deterministic, no embeddings.
 */
final readonly class DeterministicMemorySimilarityPolicy implements MemorySimilarityPolicyInterface
{
    public function __construct(
        private float $duplicateThreshold = 0.92,
        private float $conflictMinOverlap = 0.35,
        private int $subjectTokenCount = 2,
    ) {}

    public function compare(string $left, string $right): MemoryRelation
    {
        $a = $this->normalize($left);
        $b = $this->normalize($right);

        if ($a === '' || $b === '') {
            return new MemoryRelation(MemoryRelation::INDEPENDENT, 0.0);
        }

        if ($a === $b) {
            return new MemoryRelation(MemoryRelation::DUPLICATE, 1.0, ['match' => 'exact']);
        }

        $tokensA = $this->tokens($a);
        $tokensB = $this->tokens($b);
        $score = $this->jaccard($tokensA, $tokensB);

        if ($score >= $this->duplicateThreshold) {
            return new MemoryRelation(MemoryRelation::DUPLICATE, $score, ['match' => 'jaccard']);
        }

        $sharedSubject = $this->sharesSubjectPrefix($tokensA, $tokensB);
        if ($sharedSubject && $score >= 0.25) {
            return new MemoryRelation(MemoryRelation::CONFLICT, $score, ['match' => 'subject_prefix']);
        }

        if ($score >= $this->conflictMinOverlap) {
            return new MemoryRelation(MemoryRelation::CONFLICT, $score, ['match' => 'overlap']);
        }

        return new MemoryRelation(MemoryRelation::INDEPENDENT, $score);
    }

    public function normalize(string $content): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $content) ?? $content));
    }

    /**
     * @return list<string>
     */
    private function tokens(string $normalized): array
    {
        $parts = preg_split('/[^\p{L}\p{N}]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique($parts));
    }

    /**
     * @param  list<string>  $left
     * @param  list<string>  $right
     */
    private function jaccard(array $left, array $right): float
    {
        if ($left === [] || $right === []) {
            return 0.0;
        }

        $intersection = count(array_intersect($left, $right));
        $union = count(array_unique([...$left, ...$right]));

        return $union === 0 ? 0.0 : $intersection / $union;
    }

    /**
     * @param  list<string>  $left
     * @param  list<string>  $right
     */
    private function sharesSubjectPrefix(array $left, array $right): bool
    {
        $n = $this->subjectTokenCount;
        if (count($left) < $n || count($right) < $n) {
            return false;
        }

        return array_slice($left, 0, $n) === array_slice($right, 0, $n);
    }
}
