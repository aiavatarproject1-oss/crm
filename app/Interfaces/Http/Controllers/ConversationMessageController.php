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
            'character_id' => ['required_without:influencer_id', 'nullable', 'string'],
            'influencer_id' => ['required_without:character_id', 'nullable', 'string'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $conversationId = new ConversationId($id);
        $conversation = $this->conversations->find($conversationId);
        if ($conversation === null) {
            throw new ApplicationException('Conversation was not found.');
        }

        $tenantId = new TenantId((string) $validated['tenant_id']);
        $influencerId = new InfluencerId((string) ($validated['character_id'] ?? $validated['influencer_id']));
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
                $handoff = $message->metadata['handoff'] ?? null;

                return [
                    'id' => (string) $message->id(),
                    'role' => $message->sender === 'ai' ? 'assistant' : $message->sender,
                    'content' => $message->content->value,
                    'content_type' => $message->contentType,
                    'media' => self::extractMedia($message),
                    'created_at' => $message->createdAt->format(DATE_ATOM),
                    'batch_id' => $message->batchId === null ? null : (string) $message->batchId,
                    'direction' => $message->direction,
                    'vision_fail' => (bool) ($message->metadata['vision_fail'] ?? false),
                    'silent' => (bool) ($message->metadata['silent'] ?? false),
                    'handoff' => is_array($handoff) ? $handoff : null,
                ];
            }, $messages),
        ]);
    }

    /**
     * Platform-neutral media list for bots (URLs designated by CRM / AI metadata).
     *
     * @return list<array{url: string, type?: string, mime_type?: string}>
     */
    private static function extractMedia(Message $message): array
    {
        $raw = $message->metadata['media'] ?? [];
        if (! is_array($raw)) {
            return [];
        }

        $media = [];
        foreach ($raw as $entry) {
            if (is_string($entry) && $entry !== '') {
                $media[] = ['url' => $entry];

                continue;
            }
            if (! is_array($entry) || ! isset($entry['url']) || ! is_string($entry['url']) || $entry['url'] === '') {
                continue;
            }

            $item = ['url' => $entry['url']];
            if (isset($entry['type']) && is_string($entry['type'])) {
                $item['type'] = $entry['type'];
            }
            if (isset($entry['mime_type']) && is_string($entry['mime_type'])) {
                $item['mime_type'] = $entry['mime_type'];
            }
            $media[] = $item;
        }

        return $media;
    }
}
