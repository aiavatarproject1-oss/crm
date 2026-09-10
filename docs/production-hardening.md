# Production Hardening (Phase 17)

## Purpose

Operational readiness for the existing modular monolith: structured observability, correlation IDs, metrics foundation, health checks, and verified failure/safety behavior. No new product features; Memory, RAG, AI pipeline logic, and Knowledge models are unchanged.

## Observability

### Structured logging

`StructuredLoggerInterface` (`LaravelStructuredLogger`) emits event-named log records with a shared context:

| Event prefix | Where |
|--------------|--------|
| `inbound.request.*` / `inbound.batch.*` | HTTP inbound + batch ingestion |
| `ai.processing.*` | `ProcessConversationTurnHandler` |
| `queue.job.*` | `ProcessConversationTurnJob` |
| `llm.call.*` | `GenerateResponseHandler` |
| `rag.retrieval.*` | `RetrievalService` |

Secret-safe redaction drops context keys containing `api_key`, `password`, `token`, `authorization`, `secret`, `raw_payload`, `content`, `prompt`, or `messages`.

### Correlation ID

Flow:

```
HTTP (X-Correlation-ID)
  → AssignCorrelationId middleware
  → CorrelationContext + Log::withContext
  → LaravelAiProcessingDispatcher
  → ProcessConversationTurnJob($taskId, $correlationId)
  → Worker restores CorrelationContext
  → Structured logs
```

- Incoming header reused when present; otherwise a random id is generated.
- Response echoes `X-Correlation-ID`.
- `ApplicationException` JSON includes `correlation_id` (never provider secrets).

## Metrics foundation

`MetricsCollectorInterface` (`StructuredLogMetricsCollector`) records:

| Metric | Meaning |
|--------|---------|
| `ai.latency_ms` | End-to-end turn processing |
| `llm.latency_ms` / `llm.tokens_used` / `llm.failures` | LLM call outcomes |
| `queue.failures` / `queue.job.failures` | Terminal / job-level failures |
| `rag.retrieval.latency_ms` / `rag.retrieval.result_count` | Retrieval stats |

Implementation logs metric events today (swap later for Prometheus/StatsD without changing call sites).

## Health checks

`GET /api/v1/health` runs:

| Check | Probe |
|-------|--------|
| `mongodb` | Driver `ping` via configured DSN |
| `redis` | Ping queue Redis connection |
| `ollama` | `GET {OLLAMA_BASE_URL}/api/tags` (2s timeout) |
| `queue` | Sync/null → healthy; otherwise `Queue::size()` |

Response: `200` when all healthy, `503` when any check fails. Laravel’s built-in `/up` remains available.

## Failure handling

| Failure | Behavior |
|---------|----------|
| Mongo / Redis / Ollama / Queue down | Health endpoint reports unhealthy; no secrets in payload |
| LLM provider error | Wrapped as `ApplicationException('LLM response generation failed.')`; task → `FAILED`; metrics + logs without provider body |
| Queue/job failure | Task status + `queue.failures`; job logs `queue.job.failed` |
| ApplicationException on API | HTTP `503` JSON `{ success, message, correlation_id }` |

## Security review (verified)

- **Tenant / influencer isolation**: inbound auth middleware + scoped repositories (covered by existing inbound security & domain isolation tests).
- **No secret leakage**: structured logger redaction; API exception renderer omits traces; hardening tests assert provider keys never appear in client messages or redacted log context.
- **Safe error responses**: public message is always the ApplicationException text.

## Operational requirements

1. Set `LOG_CHANNEL` / `LOG_STACK` for production (e.g. `stderr` or centralized shipping).
2. Run Horizon against Redis (`QUEUE_CONNECTION=redis`) and monitor `queue.job.failures`.
3. Scrape or ship `/api/v1/health` for Mongo, Redis, Ollama, and queue liveness.
4. Propagate `X-Correlation-ID` from edge/API gateway for cross-service traces.
5. Keep `OLLAMA_*` and Mongo/Redis credentials out of application logs (redaction is defense-in-depth, not a substitute for secret management).

## Out of scope

- SaaS multi-tenant billing / admin UI  
- Redesigning Memory, RAG, Knowledge, or AI pipeline algorithms  
- Full APM / OpenTelemetry exporters (foundation only)  
