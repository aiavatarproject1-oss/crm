<?php

namespace App\Application\AI\DTO;

final readonly class LlmResponse
{
    public function __construct(public string $content, public string $model, public int $tokens_used, public int $latency_ms, public array $metadata = []) {}
}
