<?php

namespace App\Application\Observability;

use App\Application\Observability\Contracts\PipelineEventSinkInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Writes a readable end-to-end trace to storage/logs/pipeline.log
 * (inbound → media → vision → chat) and fans out to optional sinks (DB, websocket).
 */
final readonly class PipelineMonitor
{
    /**
     * @param  list<PipelineEventSinkInterface>  $sinks
     */
    public function __construct(
        private ?CorrelationContext $correlation = null,
        private array $sinks = [],
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function info(string $event, array $context = []): void
    {
        $this->write('info', $event, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function warning(string $event, array $context = []): void
    {
        $this->write('warning', $event, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function error(string $event, array $context = []): void
    {
        $this->write('error', $event, $context);
    }

    /**
     * Summarize inbound JSON for monitoring (includes media URLs + text, no binaries).
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public static function summarizeInbound(array $validated): array
    {
        $messages = [];
        foreach ((array) ($validated['messages'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $media = [];
            foreach ((array) ($item['media'] ?? []) as $entry) {
                if (is_string($entry)) {
                    $media[] = ['url' => $entry];

                    continue;
                }
                if (! is_array($entry)) {
                    continue;
                }
                $media[] = [
                    'url' => (string) ($entry['url'] ?? ''),
                    'type' => $entry['type'] ?? null,
                    'mime_type' => $entry['mime_type'] ?? null,
                ];
            }
            $messages[] = [
                'external_message_id' => $item['external_message_id'] ?? null,
                'content_type' => $item['content_type'] ?? null,
                'text' => self::clip((string) ($item['text'] ?? ''), 300),
                'media' => $media,
                'media_count' => count($media),
            ];
        }

        return [
            'tenant_id' => $validated['tenant_id'] ?? null,
            'influencer_id' => $validated['influencer_id'] ?? null,
            'platform' => $validated['platform'] ?? null,
            'external_user_id' => $validated['external_user_id'] ?? null,
            'username' => $validated['username'] ?? null,
            'has_messages_array' => isset($validated['messages']),
            'has_payload' => isset($validated['payload']),
            'message_count' => count($messages),
            'image_message_count' => count(array_filter(
                $messages,
                static fn (array $m): bool => ($m['media_count'] ?? 0) > 0
                    || in_array((string) ($m['content_type'] ?? ''), ['image'], true),
            )),
            'messages' => $messages,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function write(string $level, string $event, array $context): void
    {
        $payload = [
            'event' => $event,
            'correlation_id' => $this->correlation?->get(),
            ...$this->safe($context),
        ];

        Log::channel('pipeline')->{$level}($event, $payload);

        foreach ($this->sinks as $sink) {
            try {
                $sink->accept($level, $event, $payload);
            } catch (Throwable $exception) {
                Log::warning('pipeline.sink_failed', ['sink' => $sink::class, 'error' => $exception->getMessage()]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function safe(array $context): array
    {
        $blocked = ['api_key', 'password', 'token', 'authorization', 'secret', 'images', 'base64'];
        $clean = [];
        foreach ($context as $key => $value) {
            $lower = strtolower((string) $key);
            foreach ($blocked as $needle) {
                if (str_contains($lower, $needle)) {
                    continue 2;
                }
            }
            if (is_string($value) && strlen($value) > 2000) {
                $value = self::clip($value, 2000);
            }
            $clean[$key] = $value;
        }

        return $clean;
    }

    private static function clip(string $value, int $max): string
    {
        if (mb_strlen($value) <= $max) {
            return $value;
        }

        return mb_substr($value, 0, $max).'…';
    }
}
