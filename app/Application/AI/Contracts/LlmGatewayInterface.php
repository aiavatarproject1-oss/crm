<?php

namespace App\Application\AI\Contracts;

use App\Application\AI\DTO\LlmRequest;
use App\Application\AI\DTO\LlmResponse;

interface LlmGatewayInterface
{
    public function generate(LlmRequest $request): LlmResponse;
}
