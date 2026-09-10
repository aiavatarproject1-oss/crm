<?php

namespace App\Application\RAG;

use App\Application\AI\Contracts\EmbeddingProviderInterface;
use App\Application\Contracts\KnowledgeChunkRepositoryInterface;
use App\Application\Observability\Contracts\MetricsCollectorInterface;
use App\Application\Observability\Contracts\StructuredLoggerInterface;
use App\Application\RAG\Contracts\KnowledgeRetrieverInterface;
use App\Application\RAG\Contracts\RetrievalStrategyInterface;
use App\Application\RAG\DTO\KnowledgeSearchResult;
use App\Application\RAG\DTO\RetrievalFilter;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Knowledge\Entities\KnowledgeChunk;
use App\Domain\Tenant\ValueObjects\TenantId;

/**
 * Query → embed → vector search → filter → rank → context budget → KnowledgeSearchResult.
 */
final readonly class RetrievalService implements KnowledgeRetrieverInterface
{
    public function __construct(
        private EmbeddingProviderInterface $embeddings,
        private RetrievalStrategyInterface $strategy,
        private KnowledgeChunkRepositoryInterface $chunks,
        private float $minScore = 0.25,
        private int $maxContextTokens = 1500,
        private int $candidateMultiplier = 4,
        private ?StructuredLoggerInterface $logger = null,
        private ?MetricsCollectorInterface $metrics = null,
    ) {}

    public function retrieve(
        TenantId $tenantId,
        InfluencerId $influencerId,
        string $query,
        int $limit,
        ?RetrievalFilter $filter = null,
    ): array {
        if (trim($query) === '' || $limit < 1) {
            $this->logger?->info('rag.retrieval.empty_query', [
                'tenant_id' => (string) $tenantId,
                'influencer_id' => (string) $influencerId,
            ]);

            return [];
        }

        $started = hrtime(true);
        $filter ??= new RetrievalFilter;
        $candidateLimit = max($limit, $limit * max(1, $this->candidateMultiplier));

        $embedding = $this->embeddings->embed($query);
        $matches = $this->strategy->search($tenantId, $influencerId, $embedding->vector, $candidateLimit);

        if ($matches === []) {
            $this->metrics?->gauge('rag.retrieval.result_count', 0.0, [
                'tenant_id' => (string) $tenantId,
            ]);
            $this->logger?->info('rag.retrieval.no_matches', [
                'tenant_id' => (string) $tenantId,
                'influencer_id' => (string) $influencerId,
                'limit' => $limit,
            ]);

            return [];
        }

        $chunkIds = array_map(static fn ($match) => $match->record->chunkId, $matches);
        $chunks = $this->chunks->findByIds($tenantId, $influencerId, $chunkIds);
        $byId = [];
        foreach ($chunks as $chunk) {
            $byId[(string) $chunk->id()] = $chunk;
        }

        $ranked = [];
        foreach ($matches as $match) {
            $chunk = $byId[(string) $match->record->chunkId] ?? null;
            if ($chunk === null) {
                continue;
            }
            if ($match->score < $this->minScore) {
                continue;
            }
            if (! $this->passesMetadataFilter($chunk, $filter)) {
                continue;
            }

            $ranked[] = $this->toSearchResult($chunk, $match->score, $match->record->metadata);
        }

        $results = $this->applyContextBudget($ranked, $limit);
        $latencyMs = (hrtime(true) - $started) / 1_000_000;
        $topScore = $results[0]->score ?? null;

        $this->metrics?->timing('rag.retrieval.latency_ms', $latencyMs, [
            'tenant_id' => (string) $tenantId,
        ]);
        $this->metrics?->gauge('rag.retrieval.result_count', (float) count($results), [
            'tenant_id' => (string) $tenantId,
        ]);
        $this->logger?->info('rag.retrieval.completed', [
            'tenant_id' => (string) $tenantId,
            'influencer_id' => (string) $influencerId,
            'candidate_count' => count($matches),
            'result_count' => count($results),
            'top_score' => $topScore,
            'latency_ms' => round($latencyMs, 2),
        ]);

        return $results;
    }

    private function passesMetadataFilter(KnowledgeChunk $chunk, RetrievalFilter $filter): bool
    {
        if ($filter->documentId !== null && (string) $chunk->documentId !== $filter->documentId) {
            return false;
        }

        if ($filter->sourceId !== null) {
            $sourceId = isset($chunk->metadata['source_id']) ? (string) $chunk->metadata['source_id'] : null;
            if ($sourceId !== $filter->sourceId) {
                return false;
            }
        }

        if ($filter->category !== null) {
            $category = isset($chunk->metadata['category']) ? (string) $chunk->metadata['category'] : null;
            if ($category !== $filter->category) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $vectorMetadata
     */
    private function toSearchResult(KnowledgeChunk $chunk, float $score, array $vectorMetadata): KnowledgeSearchResult
    {
        return new KnowledgeSearchResult(
            (string) $chunk->id(),
            $chunk->content,
            $score,
            [
                'document_id' => (string) $chunk->documentId,
                'position' => $chunk->position,
                'token_count' => $chunk->tokenCount,
                'source_id' => $chunk->metadata['source_id'] ?? null,
                'category' => $chunk->metadata['category'] ?? null,
                ...$chunk->metadata,
                ...$vectorMetadata,
            ],
        );
    }

    /**
     * Keep highest-scoring chunks within token budget and result limit.
     *
     * @param  list<KnowledgeSearchResult>  $ranked
     * @return list<KnowledgeSearchResult>
     */
    private function applyContextBudget(array $ranked, int $limit): array
    {
        $selected = [];
        $tokensUsed = 0;

        foreach ($ranked as $result) {
            if (count($selected) >= $limit) {
                break;
            }

            $tokens = max(1, (int) ($result->metadata['token_count'] ?? str_word_count($result->content)));
            if ($selected !== [] && ($tokensUsed + $tokens) > $this->maxContextTokens) {
                continue;
            }

            if ($selected === [] && $tokens > $this->maxContextTokens) {
                // Always allow one best hit even if it alone exceeds budget.
                $selected[] = $result;

                break;
            }

            $selected[] = $result;
            $tokensUsed += $tokens;
        }

        return $selected;
    }
}
