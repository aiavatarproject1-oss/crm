# MongoDB Domain Design — Phase 1

## Scope

Phase 1 defines MongoDB documents, persistence boundaries, indexes, and initial reference data. It contains no controllers, AI/LLM behavior, RAG processing, Telegram integration, or conversation business rules.

All models extend `MongoDB\Laravel\Eloquent\Model`, use the `mongodb` connection, and name their collection through `$table`. MongoDB creates `_id`; Laravel manages timestamps, except `messages`, which intentionally stores only `created_at`.

## Collections

- `influencers`: `_id`, `name`, `slug`, `persona`, `language`, `active`, `settings`, timestamps.
- `users`: `_id`, `platform`, `platform_user_id`, `username`, `language`, `influencer_id`, `metadata`, `last_seen_at`, timestamps.
- `conversations`: `_id`, `user_id`, `influencer_id`, `platform`, `status`, `last_message_at`, `metadata`, timestamps.
- `messages`: `_id`, `conversation_id`, `user_id`, `role`, `text`, `platform`, `model`, `latency_ms`, `prompt_tokens`, `completion_tokens`, `tokens`, `metadata`, `created_at`.
- `memories`: `_id`, `user_id`, `influencer_id`, `type`, `content`, `importance_score`, `metadata`, timestamps. Type vocabulary: `fact`, `preference`, `emotion`, `behavior`.
- `rules`: `_id`, `name`, `type`, `patterns`, `priority`, `action`, `enabled`, `metadata`, timestamps.
- `knowledge_documents`: `_id`, `influencer_id`, `type`, `title`, `content`, `embedding_reference`, `vector_id`, `embedding_status`, `chunk_index`, `metadata`, timestamps.
- `quality_checks`: `_id`, `message_id`, `score`, `approved`, `issues`, `suggestions`, `metadata`, timestamps.
- `admin_tasks`: `_id`, `conversation_id`, `user_id`, `reason`, `status`, `priority`, `metadata`, timestamps.
- `logs`: `_id`, `level`, `service`, `event`, `payload`, timestamps.

AI- and vector-related message/document fields are persistence placeholders only. No AI or RAG integration exists in Phase 1.

## Relationships

Relationships use reference IDs instead of unbounded embedded arrays:

- `users.influencer_id` → `influencers._id`
- `conversations.user_id` → `users._id`
- `conversations.influencer_id` → `influencers._id`
- `messages.conversation_id` → `conversations._id`
- `messages.user_id` → `users._id`
- `memories.user_id` → `users._id`
- `memories.influencer_id` → `influencers._id`
- `knowledge_documents.influencer_id` → `influencers._id`
- `quality_checks.message_id` → `messages._id`
- `admin_tasks.conversation_id` → `conversations._id`
- `admin_tasks.user_id` → `users._id`

MongoDB does not enforce foreign keys. ORM relationship methods remain deferred until access patterns require them.

## Indexes

| Collection | Index |
| --- | --- |
| influencers | unique `slug` |
| users | unique `platform + platform_user_id + influencer_id`; `influencer_id` |
| conversations | `user_id`; `influencer_id`; `last_message_at` |
| messages | `conversation_id + created_at`; `created_at` |
| memories | `user_id + importance_score DESC` |
| rules | `enabled + priority DESC` |
| knowledge_documents | `influencer_id`; `vector_id` |
| quality_checks | `message_id` |
| admin_tasks | `conversation_id`; `user_id` |
| logs | `service + created_at DESC` |

Rollback removes only Phase 1 indexes and never drops collections or data.

## Seed data

`InfluencerSeeder` idempotently creates Sofia. `RuleSeeder` idempotently creates the five requested placeholder rules with empty patterns and the neutral `review` action. These rules are not executable behavior.
