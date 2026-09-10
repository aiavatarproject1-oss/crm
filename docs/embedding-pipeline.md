# Embedding Pipeline & Vector Persistence (Phase 15.2)

## Purpose

Replaceable embedding generation and Mongo vector persistence for `KnowledgeChunk` records. No queues, workers, file parsers, or retrieval-ranking upgrades.

```
KnowledgeChunk
      ↓
GenerateChunkEmbeddingService
      ↓
EmbeddingProviderInterface → EmbeddingResult
      ↓
VectorRecordRepositoryInterface
      ↓
VectorRecord (Mongo: knowledge_vectors)
```

## Embedding flow

1. Receive a `KnowledgeChunk`
2. Compute `content_hash = sha256(chunk.content)`
3. Look up existing vector by idempotency key
4. On miss: call `EmbeddingProviderInterface::embed(text)`
5. Persist `VectorRecord`
6. Return `GenerateChunkEmbeddingResult` (`created` / `duplicate`)

## Provider abstraction

`EmbeddingProviderInterface` (Application) — no Ollama types leak upward.

`EmbeddingResult` (immutable):

| Field | Meaning |
|-------|---------|
| `vector` | float list |
| `model` | provider-reported model id |
| `dimensions` | `count(vector)` |
| `metadata` | provider extras |

Infrastructure adapter: `OllamaEmbeddingProvider` (`OLLAMA_EMBEDDING_MODEL`).

## Vector persistence

`VectorRecord` fields:

- `tenant_id`, `influencer_id`, `chunk_id`
- `embedding_model`
- `vector` (+ legacy `embedding` column for older rows)
- `dimensions`
- `content_hash`
- `metadata`

Contracts:

- `VectorRecordRepositoryInterface` — `save`, `findByIdempotencyKey`, `findByChunk`
- `VectorStoreInterface` — search/rank (unchanged RAG path)

`MongoVectorStore` implements both.

## Idempotency

Unique key:

```
tenant_id + influencer_id + chunk_id + embedding_model + content_hash
```

Effects:

- Re-embedding the same chunk content with the same model → **no duplicate row**
- Same chunk + **different model** → **separate** vectors
- Same content under another tenant → separate vectors

## Failure handling

Provider/network errors are wrapped as `ApplicationException('Embedding generation failed.')` without leaking provider internals to callers.

## Out of scope

- Horizon / queue workers  
- PDF/DOCX / OCR  
- Advanced retrieval ranking  
- Qdrant migration (still a future `VectorStoreInterface` swap)  
