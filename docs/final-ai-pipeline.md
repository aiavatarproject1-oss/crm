# Final AI Decision Pipeline

## Complete lifecycle

An incoming message is first evaluated by the existing tenant- and influencer-scoped Rule Engine. `ADMIN_REVIEW` stops immediately; `BLOCK` and `IGNORE` produce a rejected final decision. Only `ALLOW_AI` continues.

For an allowed message, the Context Builder loads conversation history, user memory, influencer persona, and retrieved knowledge. The Prompt Builder converts this structured context into a provider-neutral `PromptPayload`. The LLM generates a candidate response, the Quality Checker evaluates it, and the pipeline produces a final `ResponseDecision`.

- `APPROVED`: persist the outgoing assistant message.
- `ADMIN_REVIEW`: do not persist the candidate assistant message; create an AdminTask when quality validation fails.
- `REJECTED`: stop before the LLM for blocking or ignored rule outcomes.

## Prompt assembly

`PromptBuilderInterface` separates context collection from prompt representation. `ContextPromptBuilder` currently assembles persona, memory, and retrieved knowledge into the system prompt while preserving conversation messages and structured context metadata. It makes no LLM call.

`LlmRequest::fromPromptPayload()` is the boundary between prompt assembly and the LLM gateway. A future prompt-template implementation can replace the binding without changing the decision pipeline.

## Quality validation

`QualityCheckerInterface` receives the original user message, candidate AI response, and full conversation context. `QwenQualityChecker` is an Infrastructure adapter for the local Ollama-compatible endpoint and is configured with `OLLAMA_BASE_URL` and `OLLAMA_QUALITY_MODEL`.

The adapter maps Qwen's structured result into `QualityResult`: approval, normalized score, issues, reason, and provider metadata. Quality evaluation does not persist messages or tasks.

## Admin escalation

A failed quality result creates a tenant- and influencer-scoped `AdminTask` referencing the incoming message and conversation. `MongoAdminTaskRepository` is persistence only; no review UI or workflow is included.

The compound unique index `(tenant_id, influencer_id, message_id)` makes escalation idempotent. `(tenant_id, influencer_id, status)` supports a future scoped review queue.

## DDD boundaries

- Domain owns `ResponseDecision` and `AdminTask` identity/data.
- Application orchestrates rules, context, prompting, generation, quality, and final decisions through contracts.
- Infrastructure contains Qwen/Ollama HTTP details and MongoDB persistence.
- Interface controllers remain orchestration-free consumers of Application use cases.

## Future extension points

Prompt strategies, LLM providers, quality providers, and AdminTask persistence can be replaced through their interfaces. Feature development is intentionally frozen here; future work should focus on reliability tests, observability, performance, and operational hardening rather than adding product capabilities.
