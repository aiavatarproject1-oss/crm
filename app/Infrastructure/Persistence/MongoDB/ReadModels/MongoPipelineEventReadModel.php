<?php

namespace App\Infrastructure\Persistence\MongoDB\ReadModels;

use App\Application\Admin\Contracts\PipelineEventReadModelInterface;
use App\Infrastructure\Persistence\MongoDB\Documents\PipelineEventDocument;
use DateTimeImmutable;
use DateTimeInterface;
use MongoDB\BSON\Regex;

final class MongoPipelineEventReadModel implements PipelineEventReadModelInterface
{
    public function paginate(int $page, int $perPage, array $filters = []): array
    {
        $query = PipelineEventDocument::query();

        foreach (['level', 'event', 'conversation_id', 'correlation_id'] as $field) {
            $value = $filters[$field] ?? null;
            if (is_string($value) && $value !== '') {
                $query->where($field, $value);
            }
        }

        $search = $filters['search'] ?? null;
        if (is_string($search) && trim($search) !== '') {
            $regex = new Regex(preg_quote(trim($search)), 'i');
            $query->where(function ($q) use ($regex): void {
                $q->where('event', 'regex', $regex)
                    ->orWhere('context.reply', 'regex', $regex)
                    ->orWhere('context.trigger_text', 'regex', $regex)
                    ->orWhere('context.batch_user_text', 'regex', $regex);
            });
        }

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', new DateTimeImmutable((string) $filters['from']));
        }
        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', new DateTimeImmutable((string) $filters['to']));
        }

        $total = (clone $query)->count();
        $items = $query
            ->orderByDesc('created_at')
            ->skip(max(0, $page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->map(static fn (PipelineEventDocument $doc): array => [
                'id' => (string) $doc->getAttribute('_id'),
                'level' => (string) $doc->level,
                'event' => (string) $doc->event,
                'correlation_id' => $doc->correlation_id === null ? null : (string) $doc->correlation_id,
                'conversation_id' => $doc->conversation_id === null ? null : (string) $doc->conversation_id,
                'batch_id' => $doc->batch_id === null ? null : (string) $doc->batch_id,
                'context' => (array) ($doc->context ?? []),
                'created_at' => $doc->created_at instanceof DateTimeInterface ? $doc->created_at->format(DateTimeInterface::ATOM) : (string) $doc->created_at,
            ])
            ->values()
            ->all();

        return ['items' => $items, 'total' => $total];
    }

    public function eventNames(): array
    {
        $names = PipelineEventDocument::raw(static fn ($collection) => $collection->distinct('event'));

        $list = array_map('strval', (array) $names);
        sort($list);

        return array_values($list);
    }
}
