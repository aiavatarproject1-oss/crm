<?php

namespace App\Interfaces\Http\Controllers;

use App\Application\Contracts\ConversationRepositoryInterface;
use App\Application\Contracts\MessageRepositoryInterface;
use App\Application\Exceptions\ApplicationException;
use App\Domain\Conversation\ValueObjects\ConversationId;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Message\Entities\Message;
use App\Domain\Tenant\ValueObjects\TenantId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class ConversationMessageController
{
    public function __construct(
        private ConversationRepositoryInterface $conversations,
        private MessageRepositoryInterface $messages,
    ) {}

    public function index(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'tenant_id' => ['required', 'string'],
            'influencer_id' => ['required', 'string'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $conversationId = new ConversationId($id);
        $conversation = $this->conversations->find($conversationId);
        if ($conversation === null) {
            throw new ApplicationException('Conversation was not found.');
        }

        $tenantId = new TenantId((string) $validated['tenant_id']);
        $influencerId = new InfluencerId((string) $validated['influencer_id']);
        if ((string) $conversation->tenantId !== (string) $tenantId
            || (string) $conversation->influencerId !== (string) $influencerId) {
            throw new ApplicationException('Conversation ownership validation failed.');
        }

        $messages = $this->messages->findRecentByConversation(
            $tenantId,
            $influencerId,
            $conversationId,
            (int) ($validated['limit'] ?? 50),
        );

        return response()->json([
            'success' => true,
            'data' => array_map(static function (Message $message): array {
                return [
                    'id' => (string) $message->id(),
                    'role' => $message->sender === 'ai' ? 'assistant' : $message->sender,
                    'content' => $message->content->value,
                    'created_at' => $message->createdAt->format(DATE_ATOM),
                    'batch_id' => $message->batchId === null ? null : (string) $message->batchId,
                    'direction' => $message->direction,
                ];
            }, $messages),
        ]);
    }
}
