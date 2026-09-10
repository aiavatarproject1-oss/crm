# Knowledge Domain and Persistence

## Scope

The Knowledge bounded context stores curated information owned by one tenant and one influencer. It is a persistence foundation for a future RAG system; this step performs no embedding, retrieval, similarity search, upload, or AI work.

## Document model

`KnowledgeDocument` is the aggregate root. Its immutable domain identity is `KnowledgeDocumentId`, while `KnowledgeType` classifies the content as `PERSONA`, `FAQ`, `PRODUCT`, `CAMPAIGN`, `POLICY`, `BACKGROUND`, or `PRIVATE_NOTE`. Status and source remain explicit data so later ingestion workflows can define their own lifecycle without coupling it to persistence.

Every repository operation requires both `TenantId` and `InfluencerId`. A document can never be looked up globally.

## Chunk model

`KnowledgeChunk` is a separate entity that references its parent through `KnowledgeDocumentId`. Position provides deterministic ordering and token count stores already calculated metadata. Chunking and token calculation are intentionally outside this step.

## Version strategy

Versions share one immutable `document_id`. MongoDB uses a storage-only `_id` composed from tenant, influencer, document identity, and version, allowing several immutable versions of the same logical document. A unique compound index on those fields prevents duplicate versions.

The repository exposes `findVersion(...)` for an exact version. No automatic publication, latest-version selection, or mutation workflow is implemented yet.

## Persistence and isolation

Domain entities contain no MongoDB dependency. Documents represent database records, mappers translate in both directions, and Mongo repositories implement Application contracts.

Indexes are created with stable names through MongoDB `createIndex/createIndexes`. Repeating these calls with the same definitions is idempotent.

- `knowledge_documents`: `(tenant_id, influencer_id, type, version)`
- `knowledge_documents` unique version identity: `(tenant_id, influencer_id, document_id, version)`
- `knowledge_chunks`: `(tenant_id, influencer_id, document_id, position)`

The tenant and influencer prefix prevents cross-tenant and cross-influencer query paths. Repository methods enforce that same scope explicitly.

## Future RAG integration

A later Application use case may select a document version, load its ordered chunks, and pass them to a retrieval adapter. Embeddings and vector identifiers should live in a dedicated future infrastructure capability, leaving this domain model independent from any vector database or LLM vendor.
