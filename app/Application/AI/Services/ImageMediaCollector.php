<?php

namespace App\Application\AI\Services;

use App\Domain\Message\Entities\Message;

/**
 * Collects durable image URLs from inbound message metadata (no I/O).
 */
final class ImageMediaCollector
{
    /**
     * @param  list<Message>  $messages
     * @return list<string>
     */
    public static function fromMessages(array $messages): array
    {
        $urls = [];
        foreach ($messages as $message) {
            if (! $message instanceof Message) {
                continue;
            }
            foreach (self::fromMetadata($message->metadata) as $url) {
                $urls[] = $url;
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return list<string>
     */
    public static function fromMetadata(array $metadata): array
    {
        $raw = $metadata['media'] ?? [];
        if (! is_array($raw) || $raw === []) {
            return [];
        }

        $urls = [];
        foreach ($raw as $entry) {
            if (is_string($entry)) {
                $url = trim($entry);
                if (self::isHttpUrl($url)) {
                    $urls[] = $url;
                }

                continue;
            }

            if (! is_array($entry)) {
                continue;
            }

            $url = trim((string) ($entry['url'] ?? ''));
            if (! self::isHttpUrl($url)) {
                continue;
            }

            $type = strtolower((string) ($entry['type'] ?? $entry['mime_type'] ?? 'image'));
            if ($type !== '' && ! str_contains($type, 'image') && ! str_starts_with($type, 'image/')) {
                continue;
            }

            $urls[] = $url;
        }

        return array_values(array_unique($urls));
    }

    private static function isHttpUrl(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true);
    }
}
