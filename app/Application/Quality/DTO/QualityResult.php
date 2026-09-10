<?php

namespace App\Application\Quality\DTO;

use InvalidArgumentException;

final readonly class QualityResult
{
    public function __construct(public bool $approved, public float $score, public array $issues, public string $reason, public array $metadata = [])
    {
        if ($score < 0.0 || $score > 1.0) {
            throw new InvalidArgumentException('Quality score must be between 0 and 1.');
        }
    }
}
