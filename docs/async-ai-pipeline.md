# Async AI Pipeline & Queue Architecture (Phase 16)

## Purpose

Move conversation-turn AI execution off the inbound HTTP request onto a queued worker path. Memory, RAG, LLM providers, and knowledge ingestion are unchanged — the existing `MessageAiPipelineInterface` still runs the turn.

```
Inbound Message
      ↓
Persist MessageBatch
      ↓
Create AiProcessingTask (PENDING)
      ↓
Dispatch ProcessConversationTurnJob
      ↓
Worker: ProcessConversationTurnHandler
      ↓
Existing AI Pipeline (rules → context → LLM → quality → persist)
      ↓
Task COMPLETED | FAILED | RETRYING
```

## Queue flow

1. `ReceiveIncomingMessageBatchHandler` persists user messages and `MessageBatch`.
2. It creates one `AiProcessingTask` scoped to `tenant_id + influencer_id + message_batch_id` (or reuses an existing task row).
3. `AiProcessingDispatcherInterface` (`LaravelAiProcessingDispatcher`) dispatches `ProcessConversationTurnJob`.
4. The worker loads the task, validates ownership against the batch/message, and calls `MessageAiPipelineInterface::process`.
5. Inbound **does not** call the AI pipeline synchronously.

With `QUEUE_CONNECTION=sync` (tests), the job runs inline after dispatch but still goes through the job/handler boundary. Production uses Redis + Horizon (`default` queue).

## Task lifecycle

`AiProcessingTask` statuses:

| Status | Meaning |
|--------|---------|
| `PENDING` | Created after batch persist; waiting for a worker |
| `PROCESSING` | Worker claimed the task (`attempts` incremented, `started_at` set) |
| `RETRYING` | Transient failure; queue will retry |
| `COMPLETED` | Pipeline finished (AI reply, rule block, or quality→admin review) |
| `FAILED` | Final failure after max attempts (`error` + `finished_at`) |

Fields: `id`, `tenant_id`, `influencer_id`, `conversation_id`, `message_batch_id`, `status`, `attempts`, `error`, `started_at`, `finished_at`, plus optional metadata (`user_id`, `trigger_message_id`).

## Retry behavior

`ProcessConversationTurnJob` uses `$tries = 3`.

On failure:

- `attempt < max` → status `RETRYING`, exception rethrown for the queue
- final attempt → status `FAILED` with a safe application error message, then rethrow for `failed_jobs`

Failures covered:

- LLM / provider errors (`ApplicationException`: “LLM response generation failed.”)
- Quality checker infrastructure errors
- Missing batch/message or ownership mismatch
- Other infrastructure exceptions (wrapped as “AI processing failed.”)

Quality **policy** rejection (score fail) is not a task failure: the pipeline creates an `AdminTask` and the processing task still marks `COMPLETED`.

## Idempotency

### Processing task

Unique Mongo index on `(tenant_id, influencer_id, message_batch_id)` prevents duplicate tasks for the same batch. Completed tasks short-circuit if the job is delivered twice.

### AI response

Outgoing AI messages are keyed by:

```
message_batch_id + response_type
```

`response_type` = `conversation_turn` (`AiResponseType::CONVERSATION_TURN`).

`ProcessMessageAiPipelineHandler` checks `MessageRepositoryInterface::findByBatchResponse` before generating/persisting another AI reply and stamps `metadata.response_type` + `batch_id` on the outgoing message.

## Ownership validation

The worker verifies `AiProcessingTask` tenant/influencer/conversation match the loaded `MessageBatch` and trigger `Message` before running the pipeline.

## Configuration

| Piece | Notes |
|-------|--------|
| `QUEUE_CONNECTION` | `redis` in production; `sync` in phpunit |
| Horizon | Supervises `default` queue |
| Job | `App\Jobs\ProcessConversationTurnJob` |

## Out of scope

- Redesigning Memory / RAG / LLM / Knowledge / UI  
- Production hardening beyond storing task failure status  
- Changing Horizon topology or multi-queue routing  
