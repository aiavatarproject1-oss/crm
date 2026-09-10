<?php

namespace App\Interfaces\Http\Controllers;

use App\Application\Commands\ReceiveIncomingMessageBatch\ReceiveIncomingMessageBatchCommand;
use App\Application\Commands\ReceiveIncomingMessageBatch\ReceiveIncomingMessageBatchHandler;
use App\Application\DTO\IncomingBatchMessageItem;
use App\Application\DTO\IncomingMessageBatchData;
use App\Application\Observability\Contracts\StructuredLoggerInterface;
use App\Infrastructure\Messaging\PlatformResolver;
use App\Interfaces\Http\Requests\InboundMessageRequest;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final readonly class InboundMessageController
{
    public function __construct(
        private PlatformResolver $resolver,
        private ReceiveIncomingMessageBatchHandler $handler,
        private ?StructuredLoggerInterface $logger = null,
    ) {}

    public function store(InboundMessageRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $this->logger?->info('inbound.request.received', [
            'platform' => $validated['platform'] ?? null,
            'tenant_id' => $validated['tenant_id'] ?? null,
            'influencer_id' => $validated['influencer_id'] ?? null,
            'has_messages_array' => isset($validated['messages']),
        ]);

        $batch = isset($validated['messages'])
            ? $this->batchFromMessages($validated)
            : $this->batchFromPlatformPayload($validated);

        $result = $this->handler->handle(new ReceiveIncomingMessageBatchCommand($batch));

        $this->logger?->info('inbound.request.completed', [
            'tenant_id' => $validated['tenant_id'] ?? null,
            'influencer_id' => $validated['influencer_id'] ?? null,
            'created' => $result->created,
            'duplicate' => $result->duplicate,
            'created_count' => $result->created_count,
            'status' => $result->created ? 201 : 200,
        ]);

        return response()->json(
            ['success' => true, 'data' => $result->toArray()],
            $result->created ? 201 : 200,
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function batchFromMessages(array $validated): IncomingMessageBatchData
    {
        $messages = [];
        foreach ($validated['messages'] as $item) {
            $receivedAt = isset($item['received_at'])
                ? new DateTimeImmutable((string) $item['received_at'])
                : new DateTimeImmutable;

            $messages[] = new IncomingBatchMessageItem(
                (string) $item['external_message_id'],
                (string) $item['text'],
                $receivedAt,
                (array) ($item['metadata'] ?? []),
                (array) ($item['raw_payload'] ?? []),
            );
        }

        return (new IncomingMessageBatchData(
            (string) $validated['platform'],
            (string) $validated['external_user_id'],
            isset($validated['username']) ? (string) $validated['username'] : null,
            (string) $validated['tenant_id'],
            (string) $validated['influencer_id'],
            $messages,
            (array) ($validated['metadata'] ?? []),
        ))->withOwnership((string) $validated['tenant_id'], (string) $validated['influencer_id']);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function batchFromPlatformPayload(array $validated): IncomingMessageBatchData
    {
        try {
            $adapter = $this->resolver->resolve($validated['platform']);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['platform' => ['The selected platform is not supported.']]);
        }

        $message = $adapter->normalize($validated['payload'])
            ->withOwnership($validated['tenant_id'], $validated['influencer_id']);

        return IncomingMessageBatchData::fromPlatformMessage($message);
    }
}
