<?php

namespace App\Infrastructure\Persistence\MongoDB\Repositories;

use App\Application\Admin\Contracts\AuditLogRepositoryInterface;
use App\Domain\Admin\Entities\AuditLog;
use App\Infrastructure\Persistence\MongoDB\Documents\AuditLogDocument;
use DateTimeImmutable;

final class MongoAuditLogRepository implements AuditLogRepositoryInterface
{
    public function append(AuditLog $log): void
    {
        $document = new AuditLogDocument([
            'actor_id' => $log->actorId,
            'actor_username' => $log->actorUsername,
            'action' => $log->action,
            'target_type' => $log->targetType,
            'target_id' => $log->targetId,
            'changes' => $log->changes,
            'context' => $log->context,
            'ip' => $log->ip,
            'user_agent' => $log->userAgent,
            'correlation_id' => $log->correlationId,
            'created_at' => $log->createdAt,
        ]);
        $document->setAttribute('_id', $log->id);
        $document->save();
    }

    public function paginate(int $page, int $perPage, array $filters = []): array
    {
        $query = AuditLogDocument::query();

        foreach (['actor_id', 'action', 'target_type', 'target_id'] as $field) {
            $value = $filters[$field] ?? null;
            if (is_string($value) && $value !== '') {
                $query->where($field, $value);
            }
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
            ->map(fn (AuditLogDocument $document): AuditLog => new AuditLog(
                id: (string) $document->getAttribute('_id'),
                actorId: $document->actor_id === null ? null : (string) $document->actor_id,
                actorUsername: $document->actor_username === null ? null : (string) $document->actor_username,
                action: (string) $document->action,
                targetType: $document->target_type === null ? null : (string) $document->target_type,
                targetId: $document->target_id === null ? null : (string) $document->target_id,
                changes: (array) ($document->changes ?? []),
                context: (array) ($document->context ?? []),
                ip: $document->ip === null ? null : (string) $document->ip,
                userAgent: $document->user_agent === null ? null : (string) $document->user_agent,
                correlationId: $document->correlation_id === null ? null : (string) $document->correlation_id,
                createdAt: new DateTimeImmutable((string) $document->created_at),
            ))
            ->values()
            ->all();

        return ['items' => $items, 'total' => $total];
    }
}
