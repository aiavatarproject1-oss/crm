<?php

namespace Tests\Unit\Application;

use App\Application\AI\Services\ImageMediaCollector;
use App\Infrastructure\AI\Ollama\OllamaVisionAnalyzer;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class VisionAnalysisTest extends TestCase
{
    public function test_collector_extracts_only_image_urls(): void
    {
        $urls = ImageMediaCollector::fromMetadata([
            'media' => [
                ['url' => 'https://cdn.example/a.jpg', 'type' => 'image', 'mime_type' => 'image/jpeg'],
                ['url' => 'https://cdn.example/clip.mp4', 'type' => 'video'],
                ['url' => 'not-a-url'],
                'https://cdn.example/b.png',
            ],
        ]);

        self::assertSame([
            'https://cdn.example/a.jpg',
            'https://cdn.example/b.png',
        ], $urls);
    }

    public function test_vision_analyzer_does_not_call_ollama_when_no_images(): void
    {
        Http::fake();

        $analyzer = new OllamaVisionAnalyzer(
            $this->app->make(Factory::class),
            'http://127.0.0.1:11434',
            'qwen2.5vl:7b',
            true,
            30,
        );

        self::assertNull($analyzer->analyze([]));
        self::assertNull($analyzer->analyze(['', '  ']));
        Http::assertNothingSent();
    }

    public function test_vision_analyzer_calls_ollama_when_images_present(): void
    {
        Http::fake([
            'https://cdn.example/photo.jpg' => Http::response('fake-image-bytes', 200, ['Content-Type' => 'image/jpeg']),
            'http://127.0.0.1:11434/api/chat' => Http::response([
                'model' => 'qwen2.5vl:7b',
                'message' => ['role' => 'assistant', 'content' => 'Adult male, approx mid-20s.'],
            ], 200),
        ]);

        $analyzer = new OllamaVisionAnalyzer(
            $this->app->make(Factory::class),
            'http://127.0.0.1:11434',
            'qwen2.5vl:7b',
            true,
            30,
        );

        $result = $analyzer->analyze(
            ['https://cdn.example/photo.jpg'],
            'Guess my age',
        );

        self::assertSame('Adult male, approx mid-20s.', $result);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/api/chat')
            && ($request['model'] ?? null) === 'qwen2.5vl:7b'
            && isset($request['messages'][0]['images'][0]));
    }

    public function test_prompt_builder_includes_vision_block_only_when_present(): void
    {
        $builder = new \App\Application\AI\Prompt\ContextPromptBuilder;
        $context = new \App\Application\Context\DTO\ConversationContext(
            [['role' => 'user', 'content' => 'hi']],
            [],
            new \App\Application\Context\DTO\PersonaContext(null, 'Sofia', 'en', 'warm', 'friendly', 'desc', [], []),
        );

        $without = $builder->build($context, []);
        self::assertStringNotContainsString('HARD VISION OVERRIDE', $without->system_prompt);

        $with = $builder->build($context, ['vision_analysis' => 'Looks about 25.']);
        self::assertStringContainsString('HARD VISION OVERRIDE', $with->system_prompt);
        self::assertStringContainsString('Looks about 25.', $with->system_prompt);
        self::assertStringContainsString('FORBIDDEN when vision identified', $with->system_prompt);
    }
}
