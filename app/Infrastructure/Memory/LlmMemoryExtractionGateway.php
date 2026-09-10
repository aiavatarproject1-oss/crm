<?php

namespace App\Infrastructure\Memory;

use App\Application\AI\Contracts\LlmGatewayInterface;
use App\Application\AI\DTO\LlmRequest;
use App\Application\Exceptions\ApplicationException;
use App\Application\Memory\Contracts\MemoryExtractionGatewayInterface;
use App\Application\Memory\Contracts\MemoryExtractionPromptBuilderInterface;
use App\Application\Memory\DTO\ExtractedMemoryCandidateData;
use App\Application\Memory\DTO\MemoryExtractionContext;
use Throwable;

/**
 * LLM-backed extraction gateway. Parses structured JSON into DTOs only.
 */
final readonly class LlmMemoryExtractionGateway implements MemoryExtractionGatewayInterface
{
    public function __construct(
        private LlmGatewayInterface $llm,
        private MemoryExtractionPromptBuilderInterface $prompts,
    ) {}

    public function extract(MemoryExtractionContext $context): array
    {
        $prompt = $this->prompts->build($context);

        try {
            $response = $this->llm->generate(new LlmRequest(
                conversation_id: (string) ($context->metadata['conversation_id'] ?? $context->batchId),
                messages: [['role' => 'user', 'content' => $prompt->user_prompt]],
                system_prompt: $prompt->system_prompt,
                metadata: ['purpose' => 'memory_extraction', ...$prompt->metadata],
            ));
        } catch (Throwable $exception) {
            throw new ApplicationException('Memory extraction failed.', 0, $exception);
        }

        return $this->parseCandidates((string) $response->content);
    }

    /**
     * @return list<ExtractedMemoryCandidateData>
     */
    private function parseCandidates(string $raw): array
    {
        $json = $this->extractJsonPayload($raw);
        if ($json === null) {
            throw new ApplicationException('Memory extraction failed: invalid AI output.');
        }

        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            throw new ApplicationException('Memory extraction failed: invalid AI output.');
        }

        $rows = $decoded['candidates'] ?? $decoded;
        if (! is_array($rows)) {
            throw new ApplicationException('Memory extraction failed: invalid AI output.');
        }

        // Explicit empty list is valid.
        if ($rows === []) {
            return [];
        }

        // Associative object without numeric list → malformed.
        if ($rows !== [] && ! array_is_list($rows)) {
            throw new ApplicationException('Memory extraction failed: invalid AI output.');
        }

        $candidates = [];
        foreach ($rows as $row) {
            $mapped = $this->mapRow($row);
            if ($mapped !== null) {
                $candidates[] = $mapped;
            }
        }

        return $candidates;
    }

    private function mapRow(mixed $row): ?ExtractedMemoryCandidateData
    {
        if (! is_array($row)) {
            return null;
        }

        $type = $row['type'] ?? null;
        $content = $row['content'] ?? null;
        $confidence = $row['confidence_score'] ?? $row['confidence'] ?? null;
        $importance = $row['importance_score'] ?? $row['importance'] ?? null;

        if (! is_string($type) || ! is_string($content)) {
            return null;
        }
        if (! is_numeric($confidence) || ! is_numeric($importance)) {
            return null;
        }

        return new ExtractedMemoryCandidateData(
            $type,
            $content,
            (float) $confidence,
            (float) $importance,
            is_array($row['metadata'] ?? null) ? $row['metadata'] : [],
        );
    }

    private function extractJsonPayload(string $raw): ?string
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }

        if (str_starts_with($trimmed, '```')) {
            $trimmed = preg_replace('/^```(?:json)?\s*/i', '', $trimmed) ?? $trimmed;
            $trimmed = preg_replace('/\s*```$/', '', $trimmed) ?? $trimmed;
            $trimmed = trim($trimmed);
        }

        $startObject = strpos($trimmed, '{');
        $startArray = strpos($trimmed, '[');
        if ($startObject === false && $startArray === false) {
            return null;
        }

        if ($startObject === false) {
            $start = $startArray;
        } elseif ($startArray === false) {
            $start = $startObject;
        } else {
            $start = min($startObject, $startArray);
        }

        return substr($trimmed, $start);
    }
}
