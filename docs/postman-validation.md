# Postman Validation Guide (Phase 18.1)

Manual HTTP testing surface over existing Application use cases. Dev routes are blocked in `production`.

## Import into Postman

1. **Collection:** [`docs/postman/AI-Influencer-Chat-Validation.postman_collection.json`](postman/AI-Influencer-Chat-Validation.postman_collection.json)
2. **Environment (optional):** [`docs/postman/AI-Influencer-Chat-Local.postman_environment.json`](postman/AI-Influencer-Chat-Local.postman_environment.json)

In Postman: **Import** → select both files → select environment **AI Influencer Chat — Local** → open folder **00 — Full Flow** → **Run collection**.

IDs (`tenant_id`, `influencer_id`, `task_id`, …) are written automatically by Test scripts.

Base URL: `http://localhost:8000/api`

Suggested header for all requests:

```http
Accept: application/json
Content-Type: application/json
X-Correlation-ID: postman-run-1
```

Inbound auth is off by default (`INBOUND_AUTH_ENABLED=false`).

---

## Testing order

1. `GET /v1/health`
2. `POST /v1/dev/tenants` → save `tenant_id`
3. `POST /v1/dev/influencers` → save `influencer_id`
4. `POST /v1/dev/knowledge/text`
5. `GET /v1/dev/knowledge/search`
6. `POST /v1/dev/rules` (optional)
7. `POST /v1/inbound/messages` → save `message_batch_id`, `task_id`, `conversation_id`, `user_id`
8. `GET /v1/ai/tasks/{task_id}`
9. `GET /v1/conversations/{conversation_id}/messages`
10. `POST /v1/dev/memory/extract/{message_batch_id}`
11. `GET /v1/memory/users/{user_id}`
12. `GET /v1/admin/tasks`

---

## 1. Health

`GET /api/v1/health`

**Expected 200**

```json
{
  "success": true,
  "data": {
    "healthy": true,
    "checks": [
      { "name": "mongodb", "healthy": true, "message": "..." },
      { "name": "redis", "healthy": true, "message": "..." },
      { "name": "ollama", "healthy": true, "message": "..." },
      { "name": "queue", "healthy": true, "message": "..." }
    ]
  }
}
```

Unhealthy dependencies return **503**.

---

## 2. Dev tenant / influencer

### Create tenant

`POST /api/v1/dev/tenants`

```json
{ "name": "Acme Studio" }
```

**201**

```json
{
  "success": true,
  "data": { "tenant_id": "...", "name": "Acme Studio" }
}
```

### Create influencer

`POST /api/v1/dev/influencers`

```json
{
  "tenant_id": "{{tenant_id}}",
  "name": "Sofia",
  "persona": {
    "description": "Warm lifestyle coach",
    "language": "en",
    "tone": "warm",
    "style": "friendly",
    "system_rules": ["Be respectful"]
  }
}
```

`persona` may also be a string.

**201** → `influencer_id`, `persona_id`

---

## 3. Inbound message batch

`POST /api/v1/inbound/messages`

Text-only:

```json
{
  "tenant_id": "{{tenant_id}}",
  "influencer_id": "{{influencer_id}}",
  "platform": "telegram",
  "external_user_id": "tg-user-100",
  "username": "amir",
  "messages": [
    { "external_message_id": "msg-1", "text": "Hi Sofia" },
    { "external_message_id": "msg-2", "text": "I like green tea" }
  ]
}
```

With image (JSON URL — not multipart upload). Postman request: **08b Inbound Message Batch + Image Media**. Set `media_url` in the environment/collection.

### curl (copy/paste)

Base URL: `https://resale-displace-banker.ngrok-free.dev/api`

```bash
curl --location 'https://resale-displace-banker.ngrok-free.dev/api/v1/inbound/messages' \
--header 'Accept: application/json' \
--header 'Content-Type: application/json' \
--header 'X-Correlation-ID: postman-run-1' \
--data '{
  "tenant_id": "4949281fbd8ff14572d84ffa3b9eb676",
  "influencer_id": "2daab453bc302567b1baf3d43e3cdd2d",
  "platform": "telegram",
  "external_user_id": "tg-user-100",
  "username": "amir",
  "messages": [
    {
      "external_message_id": "msg-hi-1789661008958",
      "text": "Hi Sofia"
    },
    {
      "external_message_id": "msg-photo-1789661008958",
      "content_type": "image",
      "text": "Guess my age and gender from this photo",
      "media": [
        {
          "url": "https://picsum.photos/seed/estelle-test/800/1000.jpg",
          "type": "image",
          "mime_type": "image/jpeg"
        }
      ]
    }
  ]
}'
```

JSON shape (Postman variables):

```json
{
  "tenant_id": "{{tenant_id}}",
  "influencer_id": "{{influencer_id}}",
  "platform": "telegram",
  "external_user_id": "tg-user-100",
  "username": "amir",
  "messages": [
    { "external_message_id": "msg-1", "text": "Hi Sofia" },
    {
      "external_message_id": "msg-photo-1",
      "content_type": "image",
      "text": "Guess my age and gender from this photo",
      "media": [
        {
          "url": "{{media_url}}",
          "type": "image",
          "mime_type": "image/jpeg"
        }
      ]
    }
  ]
}
```

When `media[].url` is present, the queue pipeline runs vision (`OLLAMA_VISION_MODEL`, default `qwen2.5vl:7b`) once, then the chat LLM. No image in the batch → vision is skipped.

**201**

```json
{
  "success": true,
  "data": {
    "message_batch_id": "...",
    "batch_id": "...",
    "task_id": "...",
    "conversation_id": "...",
    "user_id": "...",
    "created": true,
    "created_count": 2
  }
}
```

Flow: MessageBatch → user/conversation resolution → AiProcessingTask → queue job.

With `QUEUE_CONNECTION=sync`, AI runs inline. With Redis + Horizon, poll the task endpoint.

---

## 4. AI processing status

`GET /api/v1/ai/tasks/{{task_id}}`

**200**

```json
{
  "success": true,
  "data": {
    "id": "...",
    "status": "COMPLETED",
    "attempts": 1,
    "error": null,
    "created_at": "...",
    "completed_at": "..."
  }
}
```

Statuses: `PENDING`, `PROCESSING`, `COMPLETED`, `FAILED`, `RETRYING`.

---

## 5. Conversation messages

`GET /api/v1/conversations/{{conversation_id}}/messages?tenant_id={{tenant_id}}&influencer_id={{influencer_id}}`

**200**

```json
{
  "success": true,
  "data": [
    { "role": "user", "content": "Hi Sofia", "created_at": "...", "batch_id": "..." },
    { "role": "assistant", "content": "...", "created_at": "...", "batch_id": "..." }
  ]
}
```

---

## 6. Memory

### Trigger extraction

`POST /api/v1/dev/memory/extract/{{message_batch_id}}`

Requires a live LLM extraction gateway when using real adapters.

**200**

```json
{
  "success": true,
  "data": { "candidates_created": 2, "memories_created": 1 }
}
```

Flow: batch → extract → evaluate → consolidate.

### List active memories

`GET /api/v1/memory/users/{{user_id}}?tenant_id={{tenant_id}}&influencer_id={{influencer_id}}`

Types: `PROFILE`, `PREFERENCE`, `FACT`, `RELATIONSHIP`, `EVENT`, `GOAL`.

---

## 7. Knowledge / RAG

### Ingest text

`POST /api/v1/dev/knowledge/text`

```json
{
  "tenant_id": "{{tenant_id}}",
  "influencer_id": "{{influencer_id}}",
  "title": "Shipping FAQ",
  "content": "Orders ship within 3 business days."
}
```

**201**

```json
{
  "success": true,
  "data": {
    "source_id": "...",
    "document_id": "...",
    "chunks_count": 1,
    "vectors_created": 1
  }
}
```

Flow: KnowledgeSource → parse → chunks → embeddings → VectorRecord.

### Search

`GET /api/v1/dev/knowledge/search?tenant_id={{tenant_id}}&influencer_id={{influencer_id}}&query=shipping`

**200**

```json
{
  "success": true,
  "data": [
    { "chunk_id": "...", "content": "...", "score": 0.91 }
  ]
}
```

---

## 8. Rules

### Create

`POST /api/v1/dev/rules`

```json
{
  "tenant_id": "{{tenant_id}}",
  "influencer_id": "{{influencer_id}}",
  "type": "KEYWORD",
  "pattern": "spam",
  "decision": "BLOCK",
  "priority": 100,
  "name": "block spam"
}
```

`type`: `KEYWORD` | `REGEX`  
`decision`: `ALLOW_AI` | `ADMIN_REVIEW` | `BLOCK` | `IGNORE`

### List enabled

`GET /api/v1/dev/rules?tenant_id={{tenant_id}}&influencer_id={{influencer_id}}`

---

## 9. Admin tasks

`GET /api/v1/admin/tasks?tenant_id={{tenant_id}}&influencer_id={{influencer_id}}`

Returns pending review / quality-failure tasks (`status: pending`).

---

## Notes

- Dev routes (`/api/v1/dev/*`) return **404** when `APP_ENV=production`.
- Controllers call Application services/handlers only — no Domain bypass.
- For full AI + memory extraction, keep Ollama running and use Redis/Horizon for async turns.
