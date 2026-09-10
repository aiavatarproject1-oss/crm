<?php

namespace App\DTO;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class IncomingMessageDTO
{
    public function __construct(
        public string $platform,
        public string $external_message_id,
        public string $external_user_id,
        public ?string $username,
        public string $text,
        public ?string $language,
        public array $metadata,
        public DateTimeImmutable $received_at,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            platform: self::requiredString($data, 'platform'),
            external_message_id: self::requiredString($data, 'external_message_id'),
            external_user_id: self::requiredString($data, 'external_user_id'),
            username: self::nullableString($data['username'] ?? null),
            text: self::string($data['text'] ?? ''),
            language: self::nullableString($data['language'] ?? null),
            metadata: self::metadata($data['metadata'] ?? []),
            received_at: self::receivedAt($data['received_at'] ?? null),
        );
    }

    public function toArray(): array
    {
        return [
            'platform' => $this->platform,
            'external_message_id' => $this->external_message_id,
            'external_user_id' => $this->external_user_id,
            'username' => $this->username,
            'text' => $this->text,
            'language' => $this->language,
            'metadata' => $this->metadata,
            'received_at' => $this->received_at->format(DATE_ATOM),
        ];
    }

    private static function requiredString(array $data, string $key): string
    {
        $value = self::string($data[$key] ?? null);

        if ($value === '') {
            throw new InvalidArgumentException("The [$key] field is required.");
        }

        return $value;
    }

    private static function string(mixed $value): string
    {
        if (! is_string($value) && ! is_int($value)) {
            throw new InvalidArgumentException('Expected a string-compatible value.');
        }

        return (string) $value;
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::string($value);
    }

    private static function metadata(mixed $value): array
    {
        if (! is_array($value)) {
            throw new InvalidArgumentException('The [metadata] field must be an array.');
        }

        return $value;
    }

    private static function receivedAt(mixed $value): DateTimeImmutable
    {
        if (! $value instanceof DateTimeImmutable) {
            throw new InvalidArgumentException('The [received_at] field must be a DateTimeImmutable.');
        }

        return $value;
    }
}
