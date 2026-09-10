# Identity Resolution

Phase 13.1 hardening notes for deterministic User and Conversation resolution.

## External identity model

An inbound platform user is identified by a **stable external key**, never by username or display name.

| Field | Role |
|-------|------|
| `tenant_id` | SaaS isolation boundary |
| `platform` | Channel (`telegram`, `instagram`, `x`, `reddit`, `tiktok`) |
| `platform_user_id` | Stable external user id from the gateway adapter |

Example:

- `telegram` + `111` under `tenant-demo` → one `User` aggregate

Username, chat title, and raw payload shape are metadata only.

## User lookup strategy

`UserRepositoryInterface::findByPlatformIdentity(tenant_id, platform, platform_user_id)`

1. If a matching user exists → return it.
2. If not → create a new `User` with an explicit domain string id, then persist.

**Influencer is not part of user uniqueness.**  
The same external person talking to two influencers inside one tenant resolves to **one User**.

Tenant isolation is mandatory: the same `platform` + `platform_user_id` in another tenant is a different User.

## Conversation resolution rules

`ConversationRepositoryInterface::findActive(tenant_id, influencer_id, user_id, platform)`

Reuse the active conversation when all of these match:

- `tenant_id`
- `influencer_id`
- `user_id`
- `platform`
- `status = active`

Expected outcomes:

| Case | Result |
|------|--------|
| Same user + same influencer | Reuse active conversation |
| Same user + different influencer | New conversation |
| Different tenant | Never reuse (no cross-tenant access) |

## Idempotency behavior

Messages are idempotent on:

`tenant_id` + `influencer_id` + `platform` + `external_message_id`

Replaying the same inbound payload returns the existing message (`created=false`, `duplicate=true`) and does not create another User/Conversation/Message or re-run AI as a new ingest.

## ID strategy (Option B)

**Domain owns string identifiers; MongoDB stores them explicitly as `_id`.**

- Domain generates opaque hex ids (`bin2hex(random_bytes(16))`).
- Repositories must **not** rely on `updateOrCreate(['_id' => $id], $attrs)` alone — that path was observed to ignore the provided string id and insert a Mongo `ObjectId`.
- Persist with: set `_id` on a new document, then `save()`, or `fill`/`save` an existing document found by domain id / natural key.

This keeps:

- repository `find($id)` working with the id returned by the API
- mapper round-trips consistent
- conversation `user_id` foreign key aligned with `users._id`

## Collection naming

`mongodb/laravel-mongodb` resolves the collection from Eloquent’s `$table` property.  
A `$collection` property on Document classes is ignored and previously caused writes to land in unintended `*_documents` collections while indexes existed on `users` / `conversations` / `messages`.

Documents in this path use:

- `users`
- `conversations`
- `messages`

## Indexes

Migration: `2026_09_07_000000_create_identity_resolution_indexes.php`

| Collection | Index | Purpose |
|------------|-------|---------|
| `users` | unique `tenant_id + platform + platform_user_id` | Deterministic identity |
| `conversations` | `tenant_id + influencer_id + user_id + platform + status + last_activity_at` | Active conversation lookup |
| `messages` | unique `tenant_id + influencer_id + platform + external_message_id` | Ingest idempotency |

Indexes are created with `dropIndexIfExists` first so re-runs are migration-safe.

## Tenant isolation

Every lookup and unique index is tenant-scoped. Cross-tenant reads/writes by platform user id alone are not possible through these repositories.
