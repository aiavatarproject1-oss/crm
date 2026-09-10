# Runtime Infrastructure (Phase 13.3)

## Redis requirements

Production expects a reachable Redis server.

| Concern | Driver / connection | Redis DB (example) |
|---------|---------------------|--------------------|
| Session | `SESSION_DRIVER=redis` | `REDIS_DB=0` (default connection) |
| Queue | `QUEUE_CONNECTION=redis` → connection `queue` | `REDIS_QUEUE_DB=1` |
| Cache | `CACHE_STORE=redis` → connection `cache` | `REDIS_CACHE_DB=2` |
| Horizon meta | `HORIZON_USE=horizon` | `REDIS_HORIZON_DB=3` |

Client: `REDIS_CLIENT=predis` (default).

**Hardening rule:** if Redis is unavailable, do **not** silently switch to `file` / `database` / `sync`. Fix Redis instead.

### Current local validation note

If `tcp://127.0.0.1:6379` is refused:

- `Redis::ping()` fails
- `Cache::put()` fails when `CACHE_STORE=redis`
- Horizon cannot run
- Inbound message hot path still does **not** require Redis today (AI runs synchronously). Session/cache middleware may still fail if exercised.

## Queue / Horizon requirements

- Queue driver: Redis
- Horizon is configured (`config/horizon.php`) against Redis connection `horizon`
- `app/Jobs/` currently has no application jobs (MVP AI is sync)
- Horizon readiness = Redis up + `php artisan horizon` when async jobs are introduced later

## Environment variables

### AI / Ollama (required)

```
OLLAMA_BASE_URL=
OLLAMA_MODEL=
OLLAMA_EMBEDDING_MODEL=
OLLAMA_QUALITY_MODEL=
```

If `OLLAMA_QUALITY_MODEL` is missing at runtime, `config/services.php` falls back to `OLLAMA_MODEL`. Prefer setting it explicitly in `.env`.

### MongoDB

```
DB_CONNECTION=mongodb
MONGODB_URI=
MONGODB_DATABASE=
```

### Redis / queue / cache / session

See `.env.example` for the full Redis layout (`REDIS_*`, `QUEUE_CONNECTION`, `CACHE_STORE`, `SESSION_DRIVER`, Horizon vars).

## Failure behavior

| Failure | Behavior |
|---------|----------|
| LLM provider error | Wrapped as `ApplicationException('LLM response generation failed.')`; no AI message stored |
| Quality provider error | Wrapped as `ApplicationException('Quality evaluation failed.')`; no AI message / admin task from quality path |
| API `ApplicationException` | JSON `{ success:false, message }` with HTTP **503**; previous/provider details logged only |
| Redis down | Connection errors surface; drivers are not auto-switched |
| Validation errors | Laravel 422 responses (unchanged) |

Safe API responses must not include provider stack traces, raw Ollama payloads, or Mongo exception internals.
