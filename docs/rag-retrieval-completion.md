# RAG Retrieval Completion (Phase 15.3)

## Purpose

Production-ready retrieval quality on top of the Phase 15.2 embedding/vector layer: score thresholds, metadata filters, and a context token budget. No rerankers, hybrid search, Qdrant, Elasticsearch, knowledge graph, or UI.

```
Query
  ↓
EmbeddingProviderInterface
  ↓
RetrievalStrategyInterface  (vector search — replaceable)
  ↓
Tenant / influencer + optional metadata filters
  ↓
Minimum score cut
  ↓
Context budget (highest scores first)
  ↓
KnowledgeSearchResult[]
  ↓
Conversation context → LLM prompt
```

## Retrieval flow

1. `RetrievalService` receives scoped query text, limit, and optional `RetrievalFilter`.
2. Empty / whitespace queries (or `limit < 1`) return `[]`.
3. Query text is embedded via `EmbeddingProviderInterface`.
4. `RetrievalStrategyInterface` returns ranked vector hits (over-fetched by `candidate_multiplier`).
5. Chunks load with the same `tenant_id` + `influencer_id` scope.
6. Hits below `rag.min_score` are dropped.
7. Optional `document_id` / `source_id` / `category` filters apply on chunk fields/metadata.
8. Remaining hits stay in score order; a token budget keeps the best chunks.
9. Callers receive immutable `KnowledgeSearchResult` DTOs — never `VectorRecord`.

## Strategy abstraction

`RetrievalStrategyInterface` isolates the vector backend:

| Binding | Role |
|---------|------|
| `VectorSimilarityRetrievalStrategy` | Default — delegates to `VectorStoreInterface` |
| Future Qdrant / Atlas adapters | Swap strategy or store binding only |

`KnowledgeRetrieverInterface` stays the application entry point used by `BuildConversationContextHandler`.

## Filtering

**Always enforced**

- `tenant_id`
- `influencer_id`

(at both vector search and chunk load)

**Optional (`RetrievalFilter`)**

| Field | Source |
|-------|--------|
| `document_id` | `KnowledgeChunk.documentId` |
| `source_id` | chunk metadata `source_id` |
| `category` | chunk metadata `category` |

## Ranking

Ranking remains cosine similarity from the vector strategy (descending score). This phase does **not** add cross-encoders or hybrid BM25. Score threshold removes low-quality noise before context packing.

## Context budget

Configured by `rag.max_context_tokens` (`RAG_MAX_CONTEXT_TOKENS`).

- Walk hits in score order.
- Accumulate `token_count` from chunk metadata (fallback: word count).
- Stop when the next chunk would exceed the budget (or when `limit` is reached).
- If the single best hit alone exceeds the budget, it is still returned once so retrieval is never empty solely due to an oversized top chunk.

## Public DTO

`KnowledgeSearchResult`:

| Field | Meaning |
|-------|---------|
| `chunk_id` | Knowledge chunk identity |
| `content` | Chunk text for the prompt |
| `score` | Similarity score |
| `metadata` | document/source/category/position/token extras |

## Configuration

`config/rag.php`:

| Key | Env | Default |
|-----|-----|---------|
| `min_score` | `RAG_MIN_SCORE` | `0.25` |
| `max_context_tokens` | `RAG_MAX_CONTEXT_TOKENS` | `1500` |
| `candidate_multiplier` | `RAG_CANDIDATE_MULTIPLIER` | `4` |

## Out of scope

- Reranker models  
- Hybrid search  
- Qdrant / Elasticsearch migration  
- Knowledge graph  
- UI / async embedding workers  
