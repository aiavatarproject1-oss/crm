# MongoDB Persistence Audit (Phase 13.2)

## Collection naming convention

All MongoDB Eloquent Documents MUST declare:

```php
protected $table = 'collection_name';
```

Do **not** use `protected $collection`.  
`mongodb/laravel-mongodb` resolves the collection from Eloquent’s `$table`. A `$collection` property is ignored and previously caused writes to land in unintended `*_documents` collections.

| Document | Canonical collection |
|----------|----------------------|
| UserDocument | `users` |
| ConversationDocument | `conversations` |
| MessageDocument | `messages` |
| PersonaDocument | `personas` |
| RuleDocument | `rules` |
| MemoryDocument | `memories` |
| KnowledgeDocumentDocument | `knowledge_documents` |
| KnowledgeChunkDocument | `knowledge_chunks` |
| VectorRecordDocument | `knowledge_vectors` |
| AdminTaskDocument | `admin_tasks` |

## ID convention (Option B)

**Domain ID === MongoDB `_id` (string).**

- Domain generates / owns explicit string ids.
- On create: instantiate document → `setAttribute('_id', $domainId)` → `save()`.
- On update: `find($domainId)` (or safe natural-key fallback) → `fill` → `save()` (never regenerate `_id`).
- Shared helper: `App\Infrastructure\Persistence\MongoDB\Support\ExplicitIdPersister`.

Unsafe pattern (removed):

```php
Model::query()->updateOrCreate(['_id' => $id], $attributesWithoutId);
```

This was observed to insert a new Mongo `ObjectId` instead of the provided domain id.

### KnowledgeDocument note

Versioned knowledge rows use a **composite storage `_id`**:

`{tenant}:{influencer}:{document_id}:v{version}`

The domain `KnowledgeDocumentId` is stored in the `document_id` field and restored on hydrate. The composite `_id` is still an explicit string (not ObjectId).

## Repository save convention

1. Mapper `toDocument` sets `_id` from the domain aggregate.
2. Repository persists via `ExplicitIdPersister`.
3. Hydration reads `_id` (or `document_id` for knowledge domain id) back into Value Objects.
4. No mapper may invent a new id during round-trip.

Write-capable repositories: User, Conversation, Message, Persona, Rule, KnowledgeDocument, KnowledgeChunk, VectorStore, AdminTask.

## Index strategy

Indexes must target the **runtime** collections listed above.

Migration: `2026_09_07_130000_create_persistence_consistency_indexes.php`

| Collection | Index purpose |
|------------|---------------|
| `rules` | tenant + influencer + enabled + priority |
| `personas` | unique tenant + influencer |
| `memories` | tenant + influencer + user + importance |
| `knowledge_documents` | scope/type/version + unique document version |
| `knowledge_chunks` | scope + document + position |
| `knowledge_vectors` | scope + chunk |
| `admin_tasks` | scope + status; unique message |
| `messages` | conversation + created_at |

All index creates are preceded by `dropIndexIfExists` so migrations are idempotent.

Runtime `ensureIndexes()` in Knowledge/Admin/Vector repos also creates indexes on the corrected `$table` collections.

## Legacy collection warning

Do **not** delete these automatically. They may contain pre-13.1/13.2 data written when `$collection` was ignored:

| Legacy | Intended now |
|--------|----------------|
| `user_documents` | `users` |
| `conversation_documents` | `conversations` |
| `message_documents` | `messages` |
| `admin_task_documents` | `admin_tasks` |
| `persona_documents` | `personas` |
| `rule_documents` | `rules` |
| `memory_documents` | `memories` |
| `knowledge_document_documents` | `knowledge_documents` |
| `knowledge_chunk_documents` | `knowledge_chunks` |
| `vector_record_documents` | `knowledge_vectors` |

**Recommendation (not executed):** optional one-off copy/merge scripts from legacy → canonical collections, then verify, then archive. Never drop without backup.
