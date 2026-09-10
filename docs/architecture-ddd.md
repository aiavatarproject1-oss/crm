# Domain-Driven Design Foundation

## Scope

This step establishes namespace and dependency boundaries only. It does not move or change existing behavior, introduce business rules, create persistence adapters, or implement use cases.

## Domain layer

`App\Domain` contains the business model and language of each bounded context. Entities, value objects, domain services, domain exceptions, and repository contracts belong here. Domain code must remain independent of Laravel, databases, HTTP, queues, and external platforms.

The initial bounded-context placeholders are Influencer, User, Conversation, Message, Memory, and Rule. `Domain\Shared` contains only genuinely cross-context domain abstractions.

## Application layer

`App\Application` coordinates use cases. It will receive input through explicit commands or DTOs, invoke domain behavior, depend on domain contracts, and define transaction or orchestration boundaries. It must not contain delivery concerns or concrete persistence implementations.

## Infrastructure layer

`App\Infrastructure` contains technical implementations of contracts owned by inner layers. Future database repositories, external API clients, cache adapters, queue implementations, and framework integrations belong here. Infrastructure may depend on Application and Domain; Domain must never depend on Infrastructure.

## Interface layer

`App\Interfaces` is the delivery boundary. Future HTTP, CLI, webhook, and consumer adapters translate external input into application requests and results into external responses. This layer should remain thin and must not implement domain rules.

## Dependency direction

```text
Interfaces ──────┐
                 ▼
            Application ─────► Domain
                 ▲                ▲
                 │                │
Infrastructure ──┴────────────────┘
```

Dependencies point inward. Domain owns business abstractions; Infrastructure supplies technical implementations through dependency injection.

## Shared foundations

- `Entity` defines the identity contract for future domain entities.
- `ValueObject` defines primitive representation and value equality.
- `RepositoryInterface` is a marker for future bounded-context repository contracts; no generic CRUD API is imposed.
- `DomainException` is the base exception for future domain invariant failures.

## Service providers

`DomainServiceProvider`, `ApplicationServiceProvider`, and `InfrastructureServiceProvider` establish composition locations for their respective layers. They are intentionally empty until concrete behavior and bindings are introduced.

## Migration strategy

Existing models, repositories, services, gateways, controllers, and integrations remain in their current namespaces during this foundation step. Moving them requires a separate behavior-preserving migration plan and is outside this scope.
