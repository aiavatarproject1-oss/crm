# RAG Foundation

## Scope

This step introduces embedding, vector storage, and retrieval boundaries. It does not upload or parse files, generate chunks automatically, run background jobs, or call an LLM during retrieval.

## Embedding abstraction

`EmbeddingProviderInterface` converts text to an immutable `EmbeddingResult`. Application code only sees the vector, dimensions, model, and metadata. `OllamaEmbeddingProvider` is an Infrastructure adapter selected by dependency injection and configured through `OLLAMA_BASE_URL` and `OLLAMA_EMBEDDING_MODEL`; it is not referenced by Application code.

## Vector store abstraction

`VectorStoreInterface` stores `VectorRecord` entities and returns ranked `VectorSearchResult` values. Every search requires `TenantId` and `InfluencerId`. A vector record references a knowledge chunk but does not duplicate the chunk's content.

The initial `MongoVectorStore` persists native BSON vectors in `knowledge_vectors`. It first filters candidates by tenant and influencer and then applies cosine similarity. Its named compound index `(tenant_id, influencer_id, chunk_id)` is safe to create repeatedly.

## Retrieval lifecycle

1. `RetrievalService` receives scoped query text.
2. The configured embedding provider creates the query vector.
3. The vector store returns scoped, ranked chunk identities.
4. The chunk repository loads content using the same tenant and influencer scope.
5. Rank order and score are preserved in immutable retrieved-chunk DTOs.
6. `BuildConversationContextHandler` adds these DTOs as `knowledge_context`.
7. The AI pipeline passes that structured context into `LlmRequest`.

Retrieval makes no LLM call and does not construct prompts.

## Isolation

Both vector search and chunk loading enforce `tenant_id + influencer_id`. Filtering at both boundaries prevents a leaked or stale vector identity from loading another tenant's content.

## Qdrant migration path

A future `QdrantVectorStore` can implement `VectorStoreInterface` and replace the service-container binding. `RetrievalService`, context building, and the LLM pipeline require no changes. Provider-specific collection configuration, payload filters, and approximate-nearest-neighbor tuning stay inside that Infrastructure adapter.

For larger MongoDB deployments, the same replacement approach can introduce an Atlas `$vectorSearch` adapter while retaining the current contracts and DTOs.
