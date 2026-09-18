<?php

namespace App\Application\AI\Contracts;

use App\Application\AI\DTO\AiPipelineResult;
use App\Domain\Message\Entities\Message;
use App\Domain\User\ValueObjects\UserId;

interface MessageAiPipelineInterface
{
    /**
     * @param  array<string, mixed>  $metadata  Optional turn metadata (e.g. image_urls)
     */
    public function process(Message $message, UserId $userId, array $metadata = []): AiPipelineResult;
}
