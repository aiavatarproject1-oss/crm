<?php

namespace App\Application\Quality\Contracts;

use App\Application\Context\DTO\ConversationContext;
use App\Application\Quality\DTO\QualityResult;

interface QualityCheckerInterface
{
    public function evaluate(string $userMessage, string $aiResponse, ConversationContext $context): QualityResult;
}
