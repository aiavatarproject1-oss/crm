# AI Influencer Conversation Engine — Architecture Foundation

## Scope

This repository currently contains infrastructure and module boundaries only. It intentionally contains no conversation business logic, HTTP controllers, AI provider integrations, or messaging integrations.

## Architectural style

The application starts as a modular Laravel monolith. Each service namespace represents a capability boundary. Contracts belong in `app/Interfaces`; implementations belong in the matching `app/Services` namespace. Persistence implementations live in `app/Services/Repositories` and are registered through `RepositoryServiceProvider`.

This structure keeps Laravel as the application runtime while allowing individual capabilities to evolve independently. It also avoids premature microservices and keeps infrastructure replaceable behind contracts.

## Module responsibilities

| Namespace | Future responsibility |
| --- | --- |
| `AI` | Provider-neutral generation contracts and orchestration |
| `Conversation` | Conversation lifecycle coordination |
| `Memory` | Short- and long-term conversational memory |
| `RAG` | Retrieval and context assembly |
| `Rules` | Persona, policy, and conversation constraints |
| `Quality` | Output evaluation and quality gates |
| `Integrations` | External adapters; none are implemented yet |
| `Repositories` | MongoDB persistence implementations behind interfaces |
| `Jobs` | Asynchronous application entry points processed by Horizon |

## Dependency direction

Application code should depend on interfaces, not concrete infrastructure implementations. Controllers, provider-specific AI adapters, and Telegram adapters are deliberately outside the current foundation.

```text
Jobs / future delivery adapters
            |
            v
Conversation and capability services
            |
            v
Interfaces (contracts)
            ^
            |
MongoDB repositories / future external adapters
```

## Data infrastructure

MongoDB is the default Laravel database connection. Configure it with `MONGODB_URI` and `MONGODB_DATABASE`. The official `mongodb/laravel-mongodb` package is installed. The PHP runtime must have `ext-mongodb` enabled before database access is attempted.

Redis uses Predis so local PHP does not require `ext-redis`. Logical Redis databases are separated:

- DB 0: general Redis/session use
- DB 1: queue payloads
- DB 2: application cache
- DB 3: Horizon metadata and metrics

Production Redis Cluster deployments should use separate connections or key prefixes instead of relying on numbered logical databases because Redis Cluster supports only database 0.

## Queue and Horizon

The default queue driver is Redis. Horizon supervises the `default` queue and stores its own metadata through the `horizon` Redis connection. Queue retry time is 120 seconds; future jobs must set timeouts below that value to avoid duplicate processing.

Horizon requires `pcntl` and `posix`, so workers must run under Linux, WSL2, or a Linux container. It cannot supervise queues natively under Windows PHP.

Useful commands:

```bash
php artisan horizon
php artisan horizon:status
php artisan horizon:terminate
```

The `/horizon` dashboard is accessible locally. Before production use, populate the authorization gate in `HorizonServiceProvider`.

## Service providers

- `ServiceServiceProvider`: future service contract bindings
- `RepositoryServiceProvider`: future repository contract bindings
- `HorizonServiceProvider`: Horizon dashboard authorization and bootstrapping

Empty providers are intentional; bindings should be introduced only with real contracts.

## Runtime requirements

- PHP 8.2+
- PHP MongoDB extension (`ext-mongodb`)
- MongoDB
- Redis or Valkey
- Linux/WSL2/container runtime for Horizon (`pcntl` and `posix`)

No collections, indexes, domain models, controllers, or integration clients are included in this foundation.
