<?php

namespace App\Infrastructure\Persistence\MongoDB\Mappers;

use App\Domain\Conversation\ValueObjects\ConversationId;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Message\ValueObjects\MessageId;
use App\Domain\Quality\Entities\AdminTask;
use App\Domain\Quality\ValueObjects\AdminTaskId;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Infrastructure\Persistence\MongoDB\Documents\AdminTaskDocument;

final class AdminTaskMapper
{
    public function toDocument(AdminTask $task): AdminTaskDocument
    {
        $document = new AdminTaskDocument([
            'tenant_id' => (string) $task->tenantId,
            'influencer_id' => (string) $task->influencerId,
            'conversation_id' => (string) $task->conversationId,
            'message_id' => (string) $task->messageId,
            'reason' => $task->reason,
            'status' => $task->status,
            'metadata' => $task->metadata,
        ]);
        $document->setAttribute('_id', (string) $task->id());

        return $document;
    }

    public function toDomain(AdminTaskDocument $document): AdminTask
    {
        return new AdminTask(
            new AdminTaskId((string) $document->getAttribute('_id')),
            new TenantId((string) $document->tenant_id),
            new InfluencerId((string) $document->influencer_id),
            new ConversationId((string) $document->conversation_id),
            new MessageId((string) $document->message_id),
            (string) $document->reason,
            (string) $document->status,
            (array) ($document->metadata ?? []),
        );
    }
}
