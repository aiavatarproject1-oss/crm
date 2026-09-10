# Memory Consolidation & Conflict Resolution (Phase 14.4)

## Purpose

Prevent Memory corruption over time when a new `MemoryCandidate` overlaps existing ACTIVE memories for the same scope (`tenant + influencer + user`).

This phase manages **lifecycle decisions**. It does not add vectors, embeddings, RAG, UI, analytics, or automatic forgetting.

```
MemoryCandidate
      ↓
findActiveForUser (scoped)
      ↓
MemoryConflictDetectorInterface
      ↓
MemoryConsolidationService
      ↓
CREATE_NEW | REJECT_DUPLICATE | SUPERSEDE_EXISTING
```

## Conflict detection

`MemoryConflictDetectorInterface` compares a candidate to existing memories and returns a `MemoryConflictAssessment`:

| Relation | Meaning |
|----------|---------|
| `DUPLICATE` | Same (or near-same) durable fact already stored |
| `CONFLICT` | Same topic / overlapping fact with a different value |
| `INDEPENDENT` | New information |

Only **ACTIVE** memories in the same scope and **same `MemoryType`** participate.

## Similarity policy

`MemorySimilarityPolicyInterface` — deterministic, no embeddings.

Default: `DeterministicMemorySimilarityPolicy`

1. Normalize whitespace / case  
2. Exact match → `DUPLICATE`  
3. Token Jaccard ≥ `0.92` → `DUPLICATE`  
4. Shared subject prefix (first 2 tokens) + overlap, or Jaccard ≥ `0.35` → `CONFLICT`  
5. Otherwise → `INDEPENDENT`

Examples:

| Candidate | Existing | Result |
|-----------|----------|--------|
| Likes tea | Likes tea | DUPLICATE |
| Lives in London | Lives in Tehran | CONFLICT |
| Has a dog | Likes tea | INDEPENDENT |

## Resolution strategies

| Strategy | Action |
|----------|--------|
| `REJECT_DUPLICATE` | Do not create Memory; keep existing ACTIVE; reject pending candidate |
| `SUPERSEDE_EXISTING` | Create new ACTIVE Memory; mark old as `SUPERSEDED`; keep both |
| `CREATE_NEW` | Create new ACTIVE Memory alongside existing ones |

## Supersede lifecycle & history

Old memories are **never deleted**.

On conflict:

1. New Memory saved as ACTIVE  
2. Old Memory → `supersede(newId)` → status `SUPERSEDED`  
3. Relation metadata on **both** sides:

```json
{
  "consolidation": {
    "strategy": "SUPERSEDE_EXISTING",
    "reason": "Candidate conflicts with an existing active memory.",
    "source_memory_id": "<old>",
    "target_memory_id": "<new>",
    "relation": "CONFLICT"
  }
}
```

Context loaders that use `findActiveForUser` / `findImportantUserMemories` naturally ignore `SUPERSEDED` rows while history remains queryable.

## Influencer isolation

Consolidation loads memories with `findActiveForUser(tenant, influencer, user)`.  
The same text under another influencer is **not** a duplicate and may `CREATE_NEW`.

## Out of scope

- Memory vectors / embeddings  
- RAG changes  
- Admin UI / analytics  
- Automatic forgetting / TTL  
- Knowledge domain work  
