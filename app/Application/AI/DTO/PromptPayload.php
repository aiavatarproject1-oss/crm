<?php

namespace App\Application\AI\DTO;

final readonly class PromptPayload
{
    public function __construct(public string $system_prompt, public array $messages, public array $metadata = []) {}
}
