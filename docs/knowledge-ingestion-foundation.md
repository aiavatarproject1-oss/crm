# Knowledge Ingestion Foundation (Phase 15.1)

## Purpose

Production-ready ingestion architecture from raw knowledge input to versioned documents and ordered chunks — **without** embeddings or vector storage.

```
KnowledgeSource
      ↓
KnowledgeParserInterface (PlainTextParser)
      ↓
ParsedKnowledgeData
      ↓
ChunkingStrategyInterface (FixedTokenChunker)
      ↓
KnowledgeDocument (versioned)
      ↓
KnowledgeChunk[] (ordered)
```

Out of scope: PDF/DOCX/OCR, web crawl, queues, upload UI, vector ranking.

## Source model

`KnowledgeSource` aggregate:

| Field | Notes |
|-------|--------|
| `id` | `KnowledgeSourceId` |
| `tenant_id` / `influencer_id` | Isolation scope |
| `type` | `FILE` \| `TEXT` \| `URL` \| `API` |
| `name` | Human label |
| `checksum` | SHA-256 of payload (duplicate detection) |
| `payload` | Raw text (TEXT) or future path/URL body |
| `status` | `PENDING` → `PARSED` → `INGESTED` / `FAILED` |
| `metadata` / timestamps | Audit |

## Document extensions

`KnowledgeDocument` now also carries:

- `source_id` — link to `KnowledgeSource`
- `checksum` — content fingerprint for duplicates
- `status` — lifecycle VO (`DRAFT` / `ACTIVE` / `ARCHIVED`; legacy `"active"` accepted)
- `version` — unchanged immutable versioning (`document_id` stable, storage `_id` includes `v{N}`)

`KnowledgeChunk` stores `document_version` so chunks of v1/v2 stay distinguishable.

## Parser boundary

`KnowledgeParserInterface`

- `supports(KnowledgeSource): bool`
- `parse(KnowledgeSource): ParsedKnowledgeData`

Phase 15.1 implementation: **`PlainTextParser`** (TEXT only).

## Chunking boundary

`ChunkingStrategyInterface::chunk(ParsedKnowledgeData): KnowledgeChunkData[]`

Phase 15.1 implementation: **`FixedTokenChunker`** (whitespace tokens, configurable size/overlap). Replaceable later (semantic chunkers, etc.).

## Ingestion flow

`KnowledgeIngestionService::ingest(source, type, ?documentId, ?title)`

1. Persist source  
2. If document checksum exists in scope → **duplicate** (no new doc/chunks)  
3. Parse → mark source `PARSED`  
4. Chunk  
5. Resolve version (`1` or `latest+1` when `documentId` reused)  
6. Save `KnowledgeDocument` + ordered `KnowledgeChunk`s  
7. Mark source `INGESTED` (or `FAILED` on error)

No vectors are written.

## Isolation & versioning

- All lookups are `tenant_id + influencer_id` scoped  
- Same payload in another tenant creates a separate document  
- Re-ingesting with the same `documentId` and new checksum creates `version + 1`

## Out of scope

- Embeddings / `VectorRecord`  
- Advanced ranking  
- Binary parsers  
- Async workers / UI  
