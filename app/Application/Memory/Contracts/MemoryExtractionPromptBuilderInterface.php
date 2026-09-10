<?php

namespace App\Application\Memory\Contracts;

use App\Application\Memory\DTO\MemoryExtractionContext;
use App\Application\Memory\DTO\MemoryExtractionPrompt;

/**
 * Prompt boundary for memory extraction only — not chat PromptBuilderInterface.
 */
interface MemoryExtractionPromptBuilderInterface
{
    public function build(MemoryExtractionContext $context): MemoryExtractionPrompt;
}
