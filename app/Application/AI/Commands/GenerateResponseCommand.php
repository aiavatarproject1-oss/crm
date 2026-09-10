<?php

namespace App\Application\AI\Commands;

use App\Application\AI\DTO\LlmRequest;

final readonly class GenerateResponseCommand
{
    public function __construct(public LlmRequest $request) {}
}
