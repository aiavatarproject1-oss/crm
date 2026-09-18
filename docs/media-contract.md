# Platform-Neutral Media Contract

Bots (Instagram, Telegram, X, …) exchange media with CRM without embedding platform APIs in the core.

## Upload (`POST /api/v1/inbound/media`)

Multipart upload of an image file. CRM stores it under the public disk and returns a durable HTTPS URL for use in `media[].url` on inbound messages.

**On-disk directory:** `storage/app/public/inbound-media/{tenant}/{character}/{Y}/{m}/{d}/{ulid}.ext`  
**Public URL:** `{APP_HOST}/storage/inbound-media/...` (requires `php artisan storage:link`)

### curl example

```bash
curl --location 'https://YOUR-HOST/api/v1/inbound/media' \
  --header 'Accept: application/json' \
  --header 'X-API-Key: local-dev-inbound-key' \
  --form 'file=@/path/to/photo.jpg' \
  --form 'tenant_id=tenant-demo' \
  --form 'character_id=character-estelle'
```

Response `201`:

```json
{
  "success": true,
  "data": {
    "url": "https://YOUR-HOST/storage/inbound-media/tenant-demo/character-estelle/2026/09/18/01HZ....jpg",
    "path": "inbound-media/tenant-demo/character-estelle/2026/09/18/01HZ....jpg",
    "disk": "public",
    "mime_type": "image/jpeg",
    "size": 123456
  }
}
```

Use `data.url` as `media[].url` in the next inbound messages call.

## Inbound (`POST /api/v1/inbound/messages`)

Media is **URL-based JSON** (not multipart file upload). Put a durable public HTTPS image URL in `media[].url`.

### curl example (text + image in one turn)

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
      "text": "Hi Estelle"
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

Replace `tenant_id` / `influencer_id` / `external_message_id` / `media[].url` for your run.  
If `INBOUND_AUTH_ENABLED=true`, also send `X-API-Key: YOUR_KEY`.

Preferred batch shape:

```json
{
  "tenant_id": "…",
  "influencer_id": "…",
  "platform": "telegram",
  "external_user_id": "tg-user-100",
  "username": "amir",
  "messages": [
    {
      "external_message_id": "msg-photo-1",
      "text": "Guess my age and gender from this photo",
      "content_type": "image",
      "media": [
        { "url": "https://cdn.example/photos/abc.jpg", "type": "image", "mime_type": "image/jpeg" }
      ]
    }
  ]
}
```

Rules:

- Each message must include non-empty `text` and/or non-empty `media[]`.
- Image-only messages may omit `text`; CRM stores a placeholder (`[image]`) so domain content stays non-empty.
- Bots must **re-host** ephemeral platform CDN URLs to a durable URL before posting (Meta CDN expires quickly).
- `media` is persisted under message `metadata.media`.

### Vision (`qwen2.5vl:7b`)

- If the turn/batch has **no** image URLs → vision model is **not** called.
- If at least one image URL exists → CRM downloads it, runs `OLLAMA_VISION_MODEL` (default `qwen2.5vl:7b`), injects the analysis into the chat prompt, then the text LLM replies.
- Config: `OLLAMA_VISION_ENABLED`, `OLLAMA_VISION_MODEL`, `OLLAMA_VISION_TIMEOUT` in `.env`.
- After code changes, restart the queue worker: `php artisan queue:restart`.

## Outbound (bot poll)

1. `POST /inbound/messages` → `task_id`, `conversation_id`
2. Poll `GET /api/v1/ai/tasks/{task_id}` until `COMPLETED` / `FAILED`
3. `GET /api/v1/conversations/{id}/messages?tenant_id=&influencer_id=`

Each message includes:

| Field | Meaning |
| --- | --- |
| `content` | Text body |
| `content_type` | `text` \| `image` \| … |
| `media` | List of `{url, type?, mime_type?}` from `metadata.media` |
| `direction` | `incoming` / `outgoing` |
| `role` | `user` / `assistant` / … |

AI / system-designated photos for the user should be written into the outgoing message’s `metadata.media` (same shape). Platform bridges send each URL via their native APIs.
