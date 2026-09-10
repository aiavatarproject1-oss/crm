# Memory Domain (Phase 14.2)

## Purpose

The Memory domain stores long-lived facts about a **user** in the context of one **influencer** under one **tenant**. This phase delivers the domain foundation only: aggregate, types, lifecycle, scope rules, and repository contract.

Not in this phase:

- Memory extraction from conversation batches
- LLM-based memory creation
- Queues / workers
- RAG changes
- AI pipeline changes

## Aggregate design

`App\Domain\Memory\Entities\Memory`

| Field | Notes |
|-------|--------|
| `id` | `MemoryId` (domain id = Mongo `_id`) |
| `tenant_id` | Required scope |
| `influencer_id` | Required scope |
| `user_id` | Required scope |
| `type` | `MemoryType` VO |
| `content` | Non-empty trimmed text |
| `confidence_score` | `0.0`–`1.0` |
| `importance_score` | `0.0`–`1.0` |
| `status` | `MemoryStatus` VO |
| `source_message_batch_id` | Optional `MessageBatchId` (set by future extraction) |
| `metadata` | Free-form array |
| `created_at` / `updated_at` | Timestamps |
| `superseded_by` | Optional successor `MemoryId` when superseded |

Factories:

- `Memory::create(...)` — new ACTIVE memory with invariants
- `Memory::reconstitute(...)` — persistence hydration

## Types

`MemoryType` value object:

- `PROFILE`
- `PREFERENCE`
- `FACT`
- `RELATIONSHIP`
- `EVENT`
- `GOAL`

## Lifecycle

`MemoryStatus`:

- `ACTIVE` — eligible for context / future prompt use
- `ARCHIVED` — retained but inactive
- `SUPERSEDED` — replaced by a newer memory (terminal)

Transitions:

| Method | From | To |
|--------|------|-----|
| `activate()` | `ARCHIVED` | `ACTIVE` |
| `archive()` | `ACTIVE` | `ARCHIVED` |
| `supersede(?successor)` | `ACTIVE` / `ARCHIVED` | `SUPERSEDED` |

`SUPERSEDED` cannot be activated or archived again. Optional successor id is stored on the aggregate and mirrored in `metadata.superseded_by`.

## Scope rules

Isolation key:

```
tenant_id + influencer_id + user_id
```

Rules:

- No cross-tenant reads
- Influencer A memories are not visible to Influencer B for the same user
- Repository queries always filter by the full scope triple
- `findById` requires scope arguments so an id alone cannot cross tenants

`Memory::belongsToScope(...)` encodes the same rule at the domain level.

## Repository contract

Framework-independent: `App\Application\Contracts\MemoryRepositoryInterface`

| Operation | Behavior |
|-----------|----------|
| `save` | Persist / update by domain id |
| `findById` | Scoped lookup |
| `findActiveForUser` | `status = ACTIVE`, ordered by importance |
| `findByType` | Scoped filter by type |
| `searchByScope` | Scoped filter by optional status/type |
| `findImportantUserMemories` | Context helper → active + importance order |

Mongo adapter: `MongoMemoryRepository` + `MemoryMapper` + `memories` collection.

## Relationship to conversation batches

Inbound traffic is batch-oriented (`MessageBatch` / conversation turn). Future extraction will:

1. Receive a persisted `MessageBatch`
2. Derive candidate memories (LLM / rules — later phase)
3. Create `Memory` rows with `source_message_batch_id` set to that batch

This phase only reserves `source_message_batch_id` so extraction can attach provenance without redesigning the aggregate.

## Future extraction integration

```
MessageBatch (turn)
        ↓
[future] Memory Extraction pipeline
        ↓
Memory::create(..., sourceMessageBatchId: batch.id)
        ↓
save / supersede older conflicting ACTIVE memories
        ↓
findActiveForUser → conversation context
```

Until extraction exists, context may still read ACTIVE memories via `findImportantUserMemories`; nothing in this phase writes memories automatically.
