# Message Gateway Architecture

## Scope

This step defines only the message-ingestion boundary. Gateways do not access MongoDB, create users or conversations, dispatch jobs or events, call services, or contain application business logic.

## Why platform adapters exist

Every platform uses a different webhook shape, identifier format, timestamp representation, and field naming convention. A platform gateway contains that translation knowledge at the system boundary. The rest of the application can therefore remain independent of Telegram, Instagram, Reddit, X, or TikTok payload formats.

Each gateway implements `MessageGatewayInterface` and performs one operation:

```php
receive(array $payload): IncomingMessageDTO
```

Payload mappings are intentionally minimal and may be extended when verified platform webhook contracts are introduced. They are not API clients and do not authenticate or acknowledge webhook requests.

## Why the DTO exists

`IncomingMessageDTO` is an immutable, platform-neutral representation of an incoming message. It provides consistent names and types for platform, message identity, user identity, text, language, metadata, and receipt time.

The DTO performs structural validation only:

- platform, external message ID, and external user ID must be present;
- scalar identifiers are normalized to strings;
- metadata must be an array;
- receipt time must be a `DateTimeImmutable`.

This is boundary validation, not business validation. `toArray()` serializes `received_at` as an ISO 8601 string.

## Message flow

```text
External platform payload
          │
          ▼
Telegram / Instagram / Reddit / X / TikTok Gateway
          │  parse and normalize only
          ▼
IncomingMessageDTO
          │
          ▼
STOP (later phases define downstream handling)
```

## Current boundaries

There is deliberately no gateway service provider or automatic platform resolver in this step. Selecting a gateway belongs to a future delivery layer. Likewise, persistence, queues, events, user lookup, conversation lookup, and AI processing remain outside this implementation.
