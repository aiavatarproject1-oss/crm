<?php

namespace App\Infrastructure\Persistence\MongoDB\ReadModels;

use App\Application\Admin\Contracts\ConversationReadModelInterface;
use App\Infrastructure\Persistence\MongoDB\Documents\AdminDocument;
use App\Infrastructure\Persistence\MongoDB\Documents\AdminTaskDocument;
use App\Infrastructure\Persistence\MongoDB\Documents\ConversationDocument;
use App\Infrastructure\Persistence\MongoDB\Documents\MessageDocument;
use App\Infrastructure\Persistence\MongoDB\Documents\PersonaDocument;
use App\Infrastructure\Persistence\MongoDB\Documents\UserDocument;
use DateTimeImmutable;
use DateTimeInterface;
use MongoDB\BSON\Regex;

final class MongoConversationReadModel implements ConversationReadModelInterface
{
    public function paginate(int $page, int $perPage, array $filters = []): array
    {
        $query = ConversationDocument::query();

        foreach (['tenant_id', 'influencer_id', 'status', 'platform'] as $field) {
            $value = $filters[$field] ?? null;
            if (is_string($value) && $value !== '') {
                $query->where($field, $value);
            }
        }

        $activeWithin = $filters['active_within_minutes'] ?? null;
        if (is_int($activeWithin) && $activeWithin > 0) {
            $query->where('last_activity_at', '>=', new DateTimeImmutable("-{$activeWithin} minutes"));
        }

        $search = $filters['search'] ?? null;
        if (is_string($search) && trim($search) !== '') {
            $regex = new Regex(preg_quote(trim($search)), 'i');
            $userIds = UserDocument::query()
                ->where(function ($q) use ($regex): void {
                    $q->where('username', 'regex', $regex)->orWhere('platform_user_id', 'regex', $regex);
                })
                ->limit(200)
                ->pluck('_id')
                ->map(static fn ($id): string => (string) $id)
                ->all();
            $query->where(function ($q) use ($userIds, $search): void {
                $q->whereIn('user_id', $userIds)->orWhere('_id', trim($search));
            });
        }

        $total = (clone $query)->count();
        $conversations = $query
            ->orderByDesc('last_activity_at')
            ->skip(max(0, $page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $userIds = $conversations->pluck('user_id')->map(static fn ($id): string => (string) $id)->unique()->values()->all();
        $users = UserDocument::query()->whereIn('_id', $userIds)->get()->keyBy(static fn (UserDocument $u): string => (string) $u->getAttribute('_id'));

        $conversationIds = $conversations->map(static fn (ConversationDocument $c): string => (string) $c->getAttribute('_id'))->all();
        $lastMessages = $this->lastMessages($conversationIds);
        $messageCounts = $this->messageCounts($conversationIds);
        $personaNames = $this->personaNames($conversations->pluck('influencer_id')->map(static fn ($id): string => (string) $id)->unique()->values()->all());

        $items = [];
        foreach ($conversations as $conversation) {
            $id = (string) $conversation->getAttribute('_id');
            $items[] = $this->present($conversation, $users->get((string) $conversation->user_id), $lastMessages[$id] ?? null, $messageCounts[$id] ?? 0, $personaNames);
        }

        return ['items' => $items, 'total' => $total];
    }

    public function find(string $conversationId): ?array
    {
        $conversation = ConversationDocument::query()->find($conversationId);
        if ($conversation === null) {
            return null;
        }

        $user = UserDocument::query()->find((string) $conversation->user_id);
        $last = $this->lastMessages([$conversationId])[$conversationId] ?? null;
        $count = $this->messageCounts([$conversationId])[$conversationId] ?? 0;

        return $this->present($conversation, $user, $last, $count, $this->personaNames([(string) $conversation->influencer_id]));
    }

    public function messages(string $conversationId, int $page, int $perPage): array
    {
        $query = MessageDocument::query()->where('conversation_id', $conversationId);
        $total = (clone $query)->count();

        $items = $query
            ->orderByDesc('created_at')
            ->skip(max(0, $page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (MessageDocument $m): array => $this->presentMessage($m))
            ->all();

        return ['items' => $items, 'total' => $total];
    }

    public function dashboardStats(): array
    {
        $dayAgo = new DateTimeImmutable('-24 hours');
        $hourAgo = new DateTimeImmutable('-60 minutes');

        return [
            'conversations_total' => ConversationDocument::query()->count(),
            'conversations_active_24h' => ConversationDocument::query()->where('last_activity_at', '>=', $dayAgo)->count(),
            'conversations_live_60m' => ConversationDocument::query()->where('last_activity_at', '>=', $hourAgo)->count(),
            'messages_total' => MessageDocument::query()->count(),
            'messages_24h' => MessageDocument::query()->where('created_at', '>=', $dayAgo)->count(),
            'messages_ai_24h' => MessageDocument::query()->where('created_at', '>=', $dayAgo)->where('sender_type', 'ai')->count(),
            'users_total' => UserDocument::query()->count(),
            'review_pending' => AdminTaskDocument::query()->where('status', 'pending')->count(),
            'admins_total' => AdminDocument::query()->count(),
        ];
    }

    /**
     * @param  list<string>  $conversationIds
     * @return array<string, array<string, mixed>>
     */
    private function lastMessages(array $conversationIds): array
    {
        if ($conversationIds === []) {
            return [];
        }

        $rows = MessageDocument::raw(static fn ($collection) => $collection->aggregate([
            ['$match' => ['conversation_id' => ['$in' => $conversationIds], 'deleted_at' => null]],
            ['$sort' => ['created_at' => -1]],
            ['$group' => ['_id' => '$conversation_id', 'doc' => ['$first' => '$$ROOT']]],
        ]));

        $result = [];
        foreach ($rows as $row) {
            $doc = (array) $row['doc'];
            $result[(string) $row['_id']] = [
                'id' => (string) ($doc['_id'] ?? ''),
                'sender' => (string) ($doc['sender_type'] ?? $doc['sender'] ?? ''),
                'direction' => (string) ($doc['direction'] ?? ''),
                'content' => mb_substr((string) ($doc['content'] ?? ''), 0, 160),
                'content_type' => (string) ($doc['content_type'] ?? 'text'),
                'created_at' => self::atom($doc['created_at'] ?? null),
            ];
        }

        return $result;
    }

    /**
     * @param  list<string>  $conversationIds
     * @return array<string, int>
     */
    private function messageCounts(array $conversationIds): array
    {
        if ($conversationIds === []) {
            return [];
        }

        $rows = MessageDocument::raw(static fn ($collection) => $collection->aggregate([
            ['$match' => ['conversation_id' => ['$in' => $conversationIds], 'deleted_at' => null]],
            ['$group' => ['_id' => '$conversation_id', 'count' => ['$sum' => 1]]],
        ]));

        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row['_id']] = (int) $row['count'];
        }

        return $result;
    }

    /**
     * @param  list<string>  $influencerIds
     * @return array<string, string>
     */
    private function personaNames(array $influencerIds): array
    {
        if ($influencerIds === []) {
            return [];
        }

        $map = [];
        foreach (PersonaDocument::query()->whereIn('influencer_id', $influencerIds)->get() as $persona) {
            $map[(string) $persona->influencer_id] = (string) $persona->name;
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>|null  $lastMessage
     * @param  array<string, string>  $personaNames
     * @return array<string, mixed>
     */
    private function present(ConversationDocument $conversation, ?UserDocument $user, ?array $lastMessage, int $messageCount, array $personaNames): array
    {
        return [
            'id' => (string) $conversation->getAttribute('_id'),
            'tenant_id' => (string) $conversation->tenant_id,
            'influencer_id' => (string) $conversation->influencer_id,
            'influencer_name' => $personaNames[(string) $conversation->influencer_id] ?? null,
            'platform' => (string) $conversation->platform,
            'status' => (string) $conversation->status,
            'user' => $user === null ? null : [
                'id' => (string) $user->getAttribute('_id'),
                'username' => $user->username === null ? null : (string) $user->username,
                'platform_user_id' => (string) $user->platform_user_id,
                'language' => $user->language === null ? null : (string) $user->language,
            ],
            'message_count' => $messageCount,
            'last_message' => $lastMessage,
            'is_live' => $conversation->last_activity_at instanceof DateTimeInterface
                && $conversation->last_activity_at >= new DateTimeImmutable('-5 minutes'),
            'started_at' => self::atom($conversation->started_at),
            'last_activity_at' => self::atom($conversation->last_activity_at),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentMessage(MessageDocument $message): array
    {
        $metadata = (array) ($message->metadata ?? []);
        unset($metadata['images'], $metadata['base64']);

        return [
            'id' => (string) $message->getAttribute('_id'),
            'conversation_id' => (string) $message->conversation_id,
            'batch_id' => $message->batch_id === null ? null : (string) $message->batch_id,
            'sender' => (string) ($message->sender_type ?? $message->sender ?? ''),
            'direction' => (string) ($message->direction ?? 'incoming'),
            'content_type' => (string) ($message->content_type ?? 'text'),
            'content' => (string) $message->content,
            'platform' => (string) $message->platform,
            'external_message_id' => (string) $message->external_message_id,
            'metadata' => $metadata,
            'created_at' => self::atom($message->created_at),
        ];
    }

    private static function atom(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }
        if ($value instanceof \MongoDB\BSON\UTCDateTime) {
            return $value->toDateTime()->format(DateTimeInterface::ATOM);
        }

        try {
            return (new DateTimeImmutable((string) $value))->format(DateTimeInterface::ATOM);
        } catch (\Throwable) {
            return null;
        }
    }
}
