# Message Batch Ingestion (Phase 14.1.1)

## Why batching exists

External bots do not forward every user keystroke or line as a separate HTTP request. Users often send several short messages in a row:

```
سلام
خوبی؟
چه خبر؟
از کجایی؟
```

The bot buffers until inactivity, then posts **one** inbound request containing all lines as a single conversation turn. The service therefore ingests an ordered `messages[]` batch, not a single message.

## Relationship with the bot layer

```
User messages (platform)
        ↓
External bot buffer (inactivity window)
        ↓
POST /api/v1/inbound/messages  { messages: [...] }
        ↓
ReceiveIncomingMessageBatchHandler
        ↓
User + Conversation resolution
        ↓
Per-message persist (idempotent)
        ↓
MessageBatch (conversation turn)
        ↓
Existing AI pipeline (once per turn, last new message)
```

The bot owns buffering policy. This service owns durable identity, conversation continuity, message persistence, and turn grouping.

## Domain model

### MessageBatch (conversation turn)

Persisted aggregate representing one user turn:

- `id`
- `tenant_id`, `influencer_id`, `user_id`, `conversation_id`
- `platform`
- `message_count`
- `message_ids[]`
- `created_at` / `updated_at`
- `metadata`

### Message (unchanged aggregate)

Messages remain independent aggregates. New optional link:

```
MessageBatch
    |
    +-- Message (batch_id)
    +-- Message (batch_id)
    +-- Message (batch_id)
```

AI / admin / system messages may omit `batch_id`.

## Application contract

Primary input DTO: `IncomingMessageBatchData` + `IncomingBatchMessageItem`.

Handler: `ReceiveIncomingMessageBatchHandler`

1. Reject empty batches
2. Resolve user (`tenant + platform + external_user_id`)
3. Resolve active conversation (`tenant + influencer + user + platform`)
4. For each item: idempotent message create/lookup
5. Create `MessageBatch` when at least one message is new
6. Return `MessageBatchIngestionResult`

Compatibility:

- `ReceiveIncomingMessageHandler` wraps a single platform message into a one-item batch
- Legacy `payload` (Telegram/etc. adapter) is still accepted and converted to a one-item batch

## API shape

Preferred:

```json
{
  "tenant_id": "tenant-1",
  "influencer_id": "influencer-1",
  "platform": "telegram",
  "external_user_id": "202",
  "username": "amir",
  "messages": [
    { "external_message_id": "tg-1", "text": "hello" },
    { "external_message_id": "tg-2", "text": "how are you?" }
  ]
}
```

Legacy (still supported):

```json
{
  "tenant_id": "tenant-1",
  "influencer_id": "influencer-1",
  "platform": "telegram",
  "payload": { "...native platform update..." }
}
```

## Message persistence

- Each batch item becomes (or reuses) a `Message` row in `messages`
- New messages store `batch_id`
- Batch rows live in `message_batches` with ordered `message_ids`

## Idempotency

Still **message-level**, not batch-level:

**Key:** `tenant_id + influencer_id + platform + external_message_id`

| Resubmit | Behavior |
|----------|----------|
| Same messages twice | No duplicate messages; no second batch when everything is duplicate |
| Mixed new + duplicate | Only new messages inserted; new batch includes resolved ids (new + duplicate in that request) |
| Empty `messages` | Rejected (validation / application exception) |

## Out of scope (this phase)

- Memory extraction
- RAG write/retrieval changes
- Async queueing of turns
- AI pipeline redesign (pipeline still runs once on the last newly created message in the turn)
