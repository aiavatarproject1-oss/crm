<?php

namespace App\Infrastructure\Persistence\MongoDB\Repositories;

use App\Application\Quality\Contracts\QualityCheckRepositoryInterface;
use App\Application\Quality\DTO\QualityCheckRecord;
use App\Infrastructure\Persistence\MongoDB\Documents\QualityCheckDocument;
use App\Infrastructure\Persistence\MongoDB\Support\ExplicitIdPersister;
use DateTimeImmutable;
use MongoDB\BSON\Regex;

final class MongoQualityCheckRepository implements QualityCheckRepositoryInterface
{
    public function save(QualityCheckRecord $record): void
    {
        $doc = new QualityCheckDocument([
            'tenant_id' => $record->tenantId,
            'character_id' => $record->characterId,
            'influencer_id' => $record->characterId,
            'conversation_id' => $record->conversationId,
            'user_message_id' => $record->userMessageId,
            'user_message' => $record->userMessage,
            'ai_response' => $record->aiResponse,
            'score' => $record->score,
            'threshold' => $record->threshold,
            'approved' => $record->approved,
            'issues' => array_values($record->issues),
            'reason' => $record->reason,
            'metadata' => $record->metadata,
            'created_at' => $record->createdAt ?? (new DateTimeImmutable)->format(DATE_ATOM),
        ]);
        ExplicitIdPersister::save($doc, $record->id);
    }

    public function paginate(int $page, int $perPage, array $filters = []): array
    {
        $query = QualityCheckDocument::query()->orderByDesc('created_at');

        if (! empty($filters['character_id'])) {
            $query->where('character_id', (string) $filters['character_id']);
        }
        if (array_key_exists('approved', $filters) && $filters['approved'] !== null && $filters['approved'] !== '') {
            $query->where('approved', filter_var($filters['approved'], FILTER_VALIDATE_BOOLEAN));
        }
        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $regex = new Regex(preg_quote($search, '/'), 'i');
            $query->where(function ($q) use ($regex, $search): void {
                $q->where('user_message', 'regex', $regex)
                    ->orWhere('ai_response', 'regex', $regex)
                    ->orWhere('reason', 'regex', $regex)
                    ->orWhere('conversation_id', $search);
            });
        }

        $total = (clone $query)->count();
        $items = $query
            ->forPage(max(1, $page), max(1, $perPage))
            ->get()
            ->map(static function (QualityCheckDocument $doc): QualityCheckRecord {
                return new QualityCheckRecord(
                    id: (string) $doc->getAttribute('_id'),
                    tenantId: (string) $doc->tenant_id,
                    characterId: (string) ($doc->character_id ?? $doc->influencer_id ?? ''),
                    conversationId: (string) $doc->conversation_id,
                    userMessageId: $doc->user_message_id !== null ? (string) $doc->user_message_id : null,
                    userMessage: (string) $doc->user_message,
                    aiResponse: (string) $doc->ai_response,
                    score: (float) $doc->score,
                    threshold: (float) ($doc->threshold ?? 0.7),
                    approved: (bool) $doc->approved,
                    issues: (array) ($doc->issues ?? []),
                    reason: (string) ($doc->reason ?? ''),
                    metadata: (array) ($doc->metadata ?? []),
                    createdAt: isset($doc->created_at) ? (string) $doc->created_at : null,
                );
            })
            ->all();

        return ['items' => $items, 'total' => $total];
    }
}
