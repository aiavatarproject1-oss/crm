# Application Layer

## Responsibility

The Application layer exposes use-case boundaries and coordinates Domain objects through contracts. It defines input DTOs, commands, handlers, repository ports, and application-specific failures. It remains independent of Laravel, HTTP, queues, databases, and external integrations.

This step provides structure only. `ReceiveIncomingMessageHandler::handle()` is intentionally an empty orchestration hook because user lookup, conversation selection, message creation, persistence, and event dispatch are outside this step.

## Command and Handler pattern

`IncomingMessageData` is an immutable, transport-neutral input snapshot. `ReceiveIncomingMessageCommand` names the intent to receive that data. `ReceiveIncomingMessageHandler` is the future use-case entry point and declares its required repository ports through constructor injection.

```text
Future Interface adapter
         │
         ▼
ReceiveIncomingMessageCommand
         │
         ▼
ReceiveIncomingMessageHandler
         │
         ├── UserRepositoryInterface
         ├── ConversationRepositoryInterface
         └── MessageRepositoryInterface
                  │
                  ▼
             Domain objects
```

Commands contain no behavior. Handlers coordinate application use cases but do not contain domain rules or infrastructure implementation details.

## Application versus Domain

Domain defines business identity, invariants, aggregate behavior, value objects, and domain events. Application defines when and in what order domain capabilities are invoked for a use case. Application can depend on Domain; Domain never depends on Application.

Repository interfaces in this layer are ports required by application use cases. Future implementations belong to Infrastructure. No implementation or persistence mapping exists in this step.

## Future queue integration

A future queue consumer may construct and pass the same command to the handler. Queue jobs, serialization, retry policy, and dispatching are Interface or Infrastructure concerns and remain outside the command and handler. The handler should behave identically whether invoked synchronously or by a queued adapter.
