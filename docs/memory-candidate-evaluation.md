# Memory Candidate & Evaluation (Phase 14.3.1)

## Why candidates exist

Writing durable `Memory` rows straight from an LLM is unsafe:

- Models invent facts (hallucination)
- Confidence varies per claim
- Duplicates and contradictions appear across turns
- Empty / low-value fragments pollute context

This phase inserts a safe intermediate layer between conversation data and Memory storage:

```
MessageBatch
      ↓
MemoryExtractorInterface   (contract only — no LLM impl yet)
      ↓
MemoryCandidate[]          (PENDING proposals)
      ↓
MemoryEvaluator + policy   (APPROVE | REJECT)
      ↓
[future] Memory::create    (only from APPROVED candidates)
```

## Candidate concept

`MemoryCandidate` is a **proposal**, not a stored user memory.

| Field | Role |
|-------|------|
| `id` | `MemoryCandidateId` |
| `tenant_id` / `influencer_id` / `user_id` | Same isolation scope as Memory |
| `type` | Reuses `MemoryType` |
| `content` | Proposed text (may be empty — evaluation rejects) |
| `confidence_score` / `importance_score` | Extractor signals (`0..1`) |
| `source_message_batch_id` | Provenance to the conversation turn |
| `status` | `PENDING` → `APPROVED` \| `REJECTED` |
| `metadata` / timestamps | Audit and policy notes |

Lifecycle:

- `approve()` — only from `PENDING`
- `reject(reason)` — only from `PENDING`
- Terminal after decision (no flip-flop in this phase)

## Evaluation flow

1. `MemoryEvaluationPolicyInterface::evaluate(candidate)` → `MemoryEvaluationResult`
2. `MemoryEvaluator` applies the decision onto the aggregate (`approve` / `reject`)
3. **No** `Memory` row is created in this phase

Default policy: `ThresholdMemoryEvaluationPolicy`

| Check | Issue code |
|-------|------------|
| Empty / whitespace content | `empty_content` |
| Confidence below threshold (default `0.6`) | `low_confidence` |
| Importance below minimum (default `0.1`) | `invalid_importance` |
| Duplicate via `MemoryDuplicateDetectorInterface` | `duplicate` |

Duplicate detector: `ScopedMemoryDuplicateDetector` compares normalized content against **ACTIVE** memories in the same `tenant + influencer + user + type` scope — never across tenants.

## Extraction contract (future)

```php
interface MemoryExtractorInterface
{
    /** @return list<MemoryCandidate> */
    public function extract(MessageBatch $batch): array;
}
```

Framework-independent. **No implementation** in 14.3.1 (no AI calls, queues, or workers).

Future wiring:

```
MessageBatch (persisted turn)
        ↓
MemoryExtractorInterface::extract
        ↓
foreach candidate → MemoryEvaluator::evaluate
        ↓
APPROVED → [later] persist Memory with source_message_batch_id
REJECTED → keep audit / drop
```

## Out of scope

- LLM extraction
- Auto Memory creation
- Queues / workers
- RAG changes
- AI pipeline changes
