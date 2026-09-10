<?php

namespace App\Application\Memory\DTO;

final readonly class MemoryExtractionPrompt
{
    public function __construct(
        public string $system_prompt,
        public string $user_prompt,
        public array $metadata = [],
    ) {}
}
