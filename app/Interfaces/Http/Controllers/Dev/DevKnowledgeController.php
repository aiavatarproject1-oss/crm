<?php

namespace App\Interfaces\Http\Controllers\Dev;

use App\Application\Dev\Services\IngestTextKnowledgeService;
use App\Application\RAG\Contracts\KnowledgeRetrieverInterface;
use App\Application\RAG\DTO\KnowledgeSearchResult;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Tenant\ValueObjects\TenantId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class DevKnowledgeController
{
    public function __construct(
        private IngestTextKnowledgeService $ingest,
        private KnowledgeRetrieverInterface $retriever,
    ) {}

    public function storeText(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tenant_id' => ['required', 'string'],
            'influencer_id' => ['required', 'string'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->ingest->handle(
                (string) $validated['tenant_id'],
                (string) $validated['influencer_id'],
                (string) $validated['title'],
                (string) $validated['content'],
            ),
        ], 201);
    }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tenant_id' => ['required', 'string'],
            'influencer_id' => ['required', 'string'],
            'query' => ['required', 'string'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $results = $this->retriever->retrieve(
            new TenantId((string) $validated['tenant_id']),
            new InfluencerId((string) $validated['influencer_id']),
            (string) $validated['query'],
            (int) ($validated['limit'] ?? 5),
        );

        return response()->json([
            'success' => true,
            'data' => array_map(static fn (KnowledgeSearchResult $result): array => [
                'chunk_id' => $result->chunkId,
                'content' => $result->content,
                'score' => $result->score,
            ], $results),
        ]);
    }
}
