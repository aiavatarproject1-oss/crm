<?php

namespace App\Domain\Knowledge\ValueObjects;

use App\Domain\Shared\Exceptions\DomainException;
use App\Domain\Shared\ValueObjects\ValueObject;

final class KnowledgeSourceType extends ValueObject
{
    public const FILE = 'FILE';

    public const TEXT = 'TEXT';

    public const URL = 'URL';

    public const API = 'API';

    private const ALLOWED = [self::FILE, self::TEXT, self::URL, self::API];

    public function __construct(public readonly string $value)
    {
        if (! in_array($value, self::ALLOWED, true)) {
            throw new DomainException("Unsupported knowledge source type [$value].");
        }
    }

    public function toPrimitives(): string
    {
        return $this->value;
    }
}
