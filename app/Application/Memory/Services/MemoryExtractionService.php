<?php

namespace App\Application\Memory\Services;

use App\Application\Exceptions\ApplicationException;
use App\Application\Memory\Contracts\MemoryExtractionGatewayInterface;
use App\Application\Memory\DTO\ExtractedMemoryCandidateData;
use App\Application\Memory\DTO\MemoryExtractionContext;
use App\Domain\Memory\Entities\MemoryCandidate;
use App\Domain\Memory\ValueObjects\MemoryCandidateId;
use App\Domain\Memory\ValueObjects\MemoryType;
use App\Domain\Shared\Exceptions\DomainException;
use Throwable;

/**
 * Converts gateway extraction output into MemoryCandidate aggregates.
 * Never creates durable Memory entities.
 */
final readonly class MemoryExtractionService
{
    public function __construct(private MemoryExtractionGatewayInterface $gateway) {}

    /**
     * @return list<MemoryCandidate>
     */
    public function extract(MemoryExtractionContext $context): array
    {
        try {
            $extracted = $this->gateway->extract($context);
        } catch (ApplicationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new ApplicationException('Memory extraction failed.', 0, $exception);
        }

        if ($extracted === []) {
            return [];
        }

        $candidates = [];
        foreach ($extracted as $item) {
            if (! $item instanceof ExtractedMemoryCandidateData) {
                continue;
            }

            $candidate = $this->mapCandidate($context, $item);
            if ($candidate !== null) {
                $candidates[] = $candidate;
            }
        }

        return $candidates;
    }

    private function mapCandidate(MemoryExtractionContext $context, ExtractedMemoryCandidateData $item): ?MemoryCandidate
    {
        $type = strtoupper(trim($item->type));
        $content = $item->content;
        $confidence = $item->confidence_score;
        $importance = $item->importance_score;

        if (! is_finite($confidence) || ! is_finite($importance)) {
            return null;
        }

        try {
            return MemoryCandidate::create(
                new MemoryCandidateId(bin2hex(random_bytes(16))),
                $context->tenantId,
                $context->influencerId,
                $context->userId,
                new MemoryType($type),
                $content,
                $confidence,
                $importance,
                $context->batchId,
                [
                    'source' => 'memory_extraction',
                    ...$item->metadata,
                ],
            );
        } catch (DomainException) {
            return null;
        }
    }
}
