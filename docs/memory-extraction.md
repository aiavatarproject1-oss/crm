# Memory Extraction Engine (Phase 14.3.2)

## Purpose

Connect AI extraction to **`MemoryCandidate` only**. Durable `Memory` is never created here.

```
MessageBatch (+ messages)
        ↓
MemoryExtractionContext
        ↓
MemoryExtractionGatewayInterface
        ↓
ExtractedMemoryCandidateData[]
        ↓
MemoryExtractionService
        ↓
MemoryCandidate[] (PENDING)
        ↓
MemoryEvaluator (Phase 14.3.1)
        ↓
[future] Memory::create from APPROVED only
```

## Extraction flow

1. Build `MemoryExtractionContext` from a `MessageBatch` and its messages (`MemoryExtractionContext::fromBatch`).
2. Call `MemoryExtractionGatewayInterface::extract(context)`.
3. `MemoryExtractionService` maps valid DTOs → `MemoryCandidate` with batch scope + `source_message_batch_id`.
4. Callers may run `MemoryEvaluator` next — still no Memory persistence in this phase.

High-level entry: `MemoryExtractorInterface` → `MessageBatchMemoryExtractor` (loads recent conversation messages, filters to batch ids, delegates to the service).

## Gateway abstraction

`MemoryExtractionGatewayInterface` is framework-independent:

| Input | Output |
|-------|--------|
| `MemoryExtractionContext` | `ExtractedMemoryCandidateData[]` |

Infrastructure adapter: `LlmMemoryExtractionGateway`

- Builds prompts via `MemoryExtractionPromptBuilderInterface`
- Calls existing `LlmGatewayInterface`
- Parses JSON (`{"candidates":[...]}` or a JSON array)
- Does **not** write Memory / candidates to the database

### Prompt boundary

`MemoryExtractionPromptBuilderInterface` + `DefaultMemoryExtractionPromptBuilder`

Separate from chat `PromptBuilderInterface` / `ContextPromptBuilder`. Extraction prompts must not share the conversation-reply builder.

## Candidate creation

`ExtractedMemoryCandidateData` fields:

- `type`, `content`, `confidence_score`, `importance_score`, `metadata`

`MemoryExtractionService` rules:

- Empty gateway result → `[]` (valid)
- Invalid / unparseable AI payload → `ApplicationException`
- Malformed rows (bad type, missing fields, out-of-range scores) → skipped
- Scope (`tenant`, `influencer`, `user`, `batch`) always taken from context — never from the model

## Evaluation integration

Extraction stops at PENDING candidates. Existing evaluation:

```
foreach ($candidates as $candidate) {
    $evaluator->evaluate($candidate); // APPROVE | REJECT
}
```

Only a later phase should persist `Memory` from APPROVED candidates.

## Out of scope

- Queues / workers
- Memory consolidation
- RAG / vector memory
- Admin UI
- Direct Memory creation
