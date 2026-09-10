<?php

namespace App\Domain\AI\ValueObjects;

/**
 * Idempotency discriminator for outbound AI messages tied to a MessageBatch.
 */
final class AiResponseType
{
    public const CONVERSATION_TURN = 'conversation_turn';
}
