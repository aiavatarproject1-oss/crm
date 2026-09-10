# SaaS Domain Model

## Scope

This document describes the pure domain model only. It defines ownership and identity boundaries without persistence, framework models, repositories, controllers, APIs, or application workflows.

## Bounded contexts

### Tenant

Represents the SaaS customer account and top-level ownership boundary. A tenant is the future boundary for subscriptions, billing plans, entitlements, quotas, and account administration. Those capabilities are not implemented in this phase.

### Influencer

Represents an isolated AI identity owned by exactly one tenant. It holds persona, language, style, and behavior configuration as domain state. One tenant may own many influencers; an influencer can never belong to multiple tenants.

### User

Represents a social-platform identity interacting with one influencer. The entity carries both `TenantId` and `InfluencerId`, preventing the same external identity from becoming an unscoped global user.

### Conversation

Represents a communication session between a user and an influencer. Tenant, influencer, and user ownership are explicit typed identifiers.

### Memory

Represents long-term information associated with a user in the scope of one influencer and tenant. Memories are not shared implicitly between influencers.

### Knowledge

Reserves the boundary for influencer-owned knowledge and reference material. No knowledge entity, retrieval behavior, or infrastructure is defined yet.

### Rule

Represents conversation decision-rule state belonging to one tenant and influencer. Rule execution and decision workflows are outside this phase.

### Quality

Represents response-evaluation state scoped to a tenant, influencer, and conversation. Evaluation execution is not implemented.

### Shared

Contains only domain-wide abstractions: entity identity, immutable value-object semantics, typed identifiers, repository marker contracts, and the base domain exception. Platform or framework utilities do not belong here.

## Aggregate roots

- `Tenant` is the SaaS account aggregate root.
- `Influencer` is the AI identity aggregate root and references exactly one `TenantId`.
- `User` is the platform-user aggregate root within a tenant and influencer scope.
- `Conversation` is the communication-session aggregate root.
- `Memory` is an independently addressable long-term memory aggregate.
- `Rule` is an independently managed rule aggregate.
- `QualityEvaluation` is an independently addressable evaluation aggregate.

Aggregates reference other aggregates through typed identifiers rather than object graphs. This keeps boundaries explicit and avoids loading or mutating multiple aggregates implicitly.

## Ownership model

```text
Tenant (1)
   │
   └── Influencer (N)
          ├── User (N)
          ├── Conversation (N)
          ├── Memory (N)
          ├── Rule (N)
          ├── Knowledge (future)
          └── QualityEvaluation (N)
```

Every influencer-owned entity includes both `TenantId` and `InfluencerId`. Keeping both identifiers is deliberate: the tenant boundary is visible without resolving an influencer first, and future infrastructure can enforce tenant-scoped access consistently.

## Tenant isolation

Tenant isolation is represented at the domain boundary through typed ownership fields. IDs alone do not constitute a complete security mechanism; future application use cases and infrastructure adapters must always scope reads and writes by tenant and influencer. No cross-tenant operation is modeled in this phase.

An influencer's users, conversations, memories, rules, knowledge, and quality evaluations are isolated from those of every other influencer, including influencers owned by the same tenant. Any future sharing must be an explicit domain capability rather than an accidental persistence query.

## Identifiers and value objects

The model defines `TenantId`, `InfluencerId`, `UserId`, `ConversationId`, `MemoryId`, `RuleId`, and `QualityEvaluationId`. They share immutable value semantics through the abstract `Identifier` and `ValueObject` foundations. Identifier generation is intentionally delegated to a future application or infrastructure boundary.

## Future SaaS scalability

The tenant-first ownership model supports future plan limits, usage metering, billing, and subscription lifecycle capabilities without coupling current domain entities to a billing provider. Typed aggregate references allow persistence to be partitioned or sharded by tenant later. Influencer-level ownership also permits workload, configuration, memory, and knowledge isolation within a large tenant.

No billing aggregate, subscription workflow, database strategy, or distributed-system behavior is introduced here; those require separate domain discovery.

## Aggregate boundaries

`Conversation` and `Message` are separate aggregate roots. Conversation owns only its identity, tenant/influencer/user references, lifecycle status, start time, and last activity time. It never contains or mutates a collection of messages.

Message owns its own identity, content, sender, platform identity, external message identity, and creation time. It references a conversation only through `ConversationId`. Any operation requiring both aggregates must be coordinated by a future application use case, not by nesting Message entities inside Conversation.

This boundary prevents an indefinitely growing conversation aggregate, avoids loading message history to update conversation activity, and allows the two aggregates to evolve and scale independently.

## Domain event flow

```text
Aggregate operation
       │
       ▼
Pure PHP domain event recorded in aggregate memory
       │
       ▼
releaseDomainEvents()
       │
       ▼
STOP — dispatch and handling belong to a future application/infrastructure step
```

Conversation records `ConversationStarted` and `ConversationUpdated`. Message records `MessageCreated`. `RuleTriggered`, `MemoryCreated`, and `QualityCheckRequested` define domain facts for their contexts without any dispatcher or framework dependency.

All events carry tenant and influencer identifiers so future handlers cannot lose ownership context. Events are immutable pure PHP objects and have no Laravel Event, database, queue, or transport dependency.
