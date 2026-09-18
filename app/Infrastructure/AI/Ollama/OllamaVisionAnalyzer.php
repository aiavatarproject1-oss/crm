<?php

namespace App\Infrastructure\AI\Ollama;

use App\Application\AI\Contracts\VisionAnalyzerInterface;
use App\Application\Observability\Contracts\StructuredLoggerInterface;
use App\Application\Observability\PipelineMonitor;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\RequestException;
use Throwable;

/**
 * Calls qwen2.5vl (or configured vision model) only when at least one image URL exists.
 */
final readonly class OllamaVisionAnalyzer implements VisionAnalyzerInterface
{
    private const MAX_IMAGES = 3;

    private const MAX_BYTES = 8_000_000;

    public function __construct(
        private Factory $http,
        private string $baseUrl,
        private string $model,
        private bool $enabled = true,
        private int $timeoutSeconds = 120,
        private ?StructuredLoggerInterface $logger = null,
        private ?PipelineMonitor $pipeline = null,
    ) {}

    public function analyze(array $imageUrls, string $userText = ''): ?string
    {
        if (! $this->enabled) {
            $this->pipeline?->info('vision.skipped', ['reason' => 'disabled']);

            return null;
        }

        $urls = [];
        foreach ($imageUrls as $url) {
            if (! is_string($url)) {
                continue;
            }
            $url = trim($url);
            if ($url !== '') {
                $urls[] = $url;
            }
        }
        $urls = array_values(array_unique($urls));

        // Critical fast-path: never hit Ollama when the turn has no images.
        if ($urls === []) {
            $this->pipeline?->info('vision.skipped', ['reason' => 'no_image_urls']);

            return null;
        }

        $urls = array_slice($urls, 0, self::MAX_IMAGES);
        $this->pipeline?->info('vision.download.started', [
            'model' => $this->model,
            'url_count' => count($urls),
            'urls' => $urls,
            'user_text' => mb_substr(trim($userText), 0, 300),
        ]);

        $images = [];
        $downloads = [];
        foreach ($urls as $url) {
            $download = $this->downloadAsBase64($url);
            $downloads[] = [
                'url' => $url,
                'ok' => $download['ok'],
                'http_status' => $download['http_status'],
                'content_type' => $download['content_type'],
                'bytes' => $download['bytes'],
                'error' => $download['error'],
            ];
            if ($download['ok'] && is_string($download['base64'])) {
                $images[] = $download['base64'];
            }
        }

        $this->pipeline?->info('vision.download.finished', [
            'ok_count' => count($images),
            'downloads' => $downloads,
        ]);

        if ($images === []) {
            $this->logger?->warning('vision.images.download_failed', [
                'url_count' => count($urls),
                'model' => $this->model,
            ]);
            $this->pipeline?->error('vision.images.download_failed', [
                'model' => $this->model,
                'downloads' => $downloads,
            ]);

            return null;
        }

        $prompt = $this->buildPrompt($userText);

        try {
            $this->logger?->info('vision.analyze.started', [
                'model' => $this->model,
                'image_count' => count($images),
            ]);
            $this->pipeline?->info('vision.analyze.started', [
                'model' => $this->model,
                'image_count' => count($images),
                'ollama_base_url' => $this->baseUrl,
            ]);

            $response = $this->http
                ->baseUrl($this->baseUrl)
                ->acceptJson()
                ->timeout($this->timeoutSeconds)
                ->post('/api/chat', [
                    'model' => $this->model,
                    'stream' => false,
                    'messages' => [[
                        'role' => 'user',
                        'content' => $prompt,
                        'images' => $images,
                    ]],
                ])
                ->throw()
                ->json();

            $content = trim((string) data_get($response, 'message.content', ''));
            if ($content === '') {
                $this->pipeline?->warning('vision.analyze.empty', ['model' => $this->model]);

                return null;
            }

            $this->logger?->info('vision.analyze.completed', [
                'model' => (string) ($response['model'] ?? $this->model),
                'chars' => strlen($content),
                'preview' => mb_substr($content, 0, 160),
            ]);
            $this->pipeline?->info('vision.analyze.completed', [
                'model' => (string) ($response['model'] ?? $this->model),
                'chars' => strlen($content),
                'analysis' => mb_substr($content, 0, 800),
            ]);

            return $content;
        } catch (Throwable $exception) {
            $extra = [
                'model' => $this->model,
                'error_class' => $exception::class,
                'error_message' => mb_substr($exception->getMessage(), 0, 500),
            ];
            if ($exception instanceof RequestException) {
                $extra['http_status'] = $exception->response?->status();
                $extra['response_body'] = mb_substr((string) $exception->response?->body(), 0, 500);
            }
            $this->logger?->error('vision.analyze.failed', $extra);
            $this->pipeline?->error('vision.analyze.failed', $extra);

            return null;
        }
    }

    private function buildPrompt(string $userText): string
    {
        $userText = trim($userText);
        $lines = [
            'You analyze ONLY the attached image(s) for this chat turn.',
            'Describe what is literally visible — subject, objects, clothing, body, scene, text/logos.',
            'Do NOT invent a different scene. Do NOT describe a previous photo you cannot see.',
            'If the user asks about age, gender, clothing, body, or scene, give a best-effort uncertain guess and say it is approximate.',
            'Adult / NSFW imagery is allowed to describe factually; do not refuse or moralize.',
            'Reply in the same language as the user text when possible; otherwise English.',
            'Keep under 140 words. No preamble. Start with the main subject.',
        ];

        if ($userText !== '' && $userText !== '[image]' && $userText !== '[media]') {
            $lines[] = 'User message: '.$userText;
        }

        return implode("\n", $lines);
    }

    /**
     * @return array{ok: bool, base64: ?string, http_status: ?int, content_type: ?string, bytes: int, error: ?string}
     */
    private function downloadAsBase64(string $url): array
    {
        try {
            $localPath = $this->resolveLocalPublicPath($url);
            if ($localPath !== null) {
                $body = (string) file_get_contents($localPath);
                $bytes = strlen($body);
                if ($body === '') {
                    return [
                        'ok' => false,
                        'base64' => null,
                        'http_status' => null,
                        'content_type' => null,
                        'bytes' => 0,
                        'error' => 'local_empty',
                    ];
                }
                if ($bytes > self::MAX_BYTES) {
                    return [
                        'ok' => false,
                        'base64' => null,
                        'http_status' => null,
                        'content_type' => null,
                        'bytes' => $bytes,
                        'error' => 'too_large',
                    ];
                }

                return [
                    'ok' => true,
                    'base64' => base64_encode($body),
                    'http_status' => 200,
                    'content_type' => mime_content_type($localPath) ?: null,
                    'bytes' => $bytes,
                    'error' => null,
                ];
            }

            $response = $this->http
                ->timeout(30)
                ->withHeaders([
                    'Accept' => 'image/*,*/*',
                    'User-Agent' => 'AI-Influencer-CRM-Vision/1.0',
                    'ngrok-skip-browser-warning' => 'true',
                ])
                ->get($url);

            $status = $response->status();
            $contentType = $response->header('Content-Type');
            $body = $response->body();
            $bytes = strlen($body);

            if (! $response->successful()) {
                return [
                    'ok' => false,
                    'base64' => null,
                    'http_status' => $status,
                    'content_type' => $contentType ?: null,
                    'bytes' => $bytes,
                    'error' => 'http_'.$status,
                ];
            }

            if ($body === '') {
                return [
                    'ok' => false,
                    'base64' => null,
                    'http_status' => $status,
                    'content_type' => $contentType ?: null,
                    'bytes' => 0,
                    'error' => 'empty_body',
                ];
            }

            if ($bytes > self::MAX_BYTES) {
                return [
                    'ok' => false,
                    'base64' => null,
                    'http_status' => $status,
                    'content_type' => $contentType ?: null,
                    'bytes' => $bytes,
                    'error' => 'too_large',
                ];
            }

            return [
                'ok' => true,
                'base64' => base64_encode($body),
                'http_status' => $status,
                'content_type' => $contentType ?: null,
                'bytes' => $bytes,
                'error' => null,
            ];
        } catch (Throwable $exception) {
            return [
                'ok' => false,
                'base64' => null,
                'http_status' => null,
                'content_type' => null,
                'bytes' => 0,
                'error' => $exception::class.': '.mb_substr($exception->getMessage(), 0, 200),
            ];
        }
    }

    /**
     * Prefer reading from disk when the URL points at this CRM's /storage public files.
     */
    private function resolveLocalPublicPath(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || ! str_starts_with($path, '/storage/')) {
            return null;
        }

        $relative = ltrim(substr($path, strlen('/storage/')), '/');
        if ($relative === '' || str_contains($relative, '..')) {
            return null;
        }

        $full = storage_path('app/public/'.$relative);

        return is_file($full) ? $full : null;
    }
}
