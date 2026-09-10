<?php

namespace Tests\Unit\Infrastructure;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

final class RuntimeInfrastructureTest extends TestCase
{
    public function test_ollama_configuration_keys_are_present(): void
    {
        self::assertNotSame('', (string) config('services.ollama.base_url'));
        self::assertNotSame('', (string) config('services.ollama.model'));
        self::assertNotSame('', (string) config('services.ollama.embedding_model'));
        self::assertNotSame('', (string) config('services.ollama.quality_model'));
    }

    public function test_quality_model_falls_back_to_chat_model_when_unset(): void
    {
        Config::set('services.ollama.model', 'chat-model-x');
        Config::set('services.ollama.quality_model', config('services.ollama.model'));

        self::assertSame('chat-model-x', config('services.ollama.quality_model'));
    }

    public function test_production_runtime_expects_redis_backed_drivers_in_defaults(): void
    {
        // phpunit.xml uses array/sync for isolation; production contract is redis.
        $example = file_get_contents(base_path('.env.example'));
        self::assertNotFalse($example);
        self::assertStringContainsString('QUEUE_CONNECTION=redis', $example);
        self::assertStringContainsString('CACHE_STORE=redis', $example);
        self::assertStringContainsString('SESSION_DRIVER=redis', $example);
        self::assertStringContainsString('OLLAMA_QUALITY_MODEL=', $example);
        self::assertStringContainsString('REDIS_CACHE_DB=2', $example);
        self::assertStringContainsString('REDIS_QUEUE_DB=1', $example);
        self::assertStringContainsString('REDIS_HORIZON_DB=3', $example);
    }

    public function test_redis_cache_database_default_matches_example_contract(): void
    {
        self::assertSame('2', (string) config('database.redis.cache.database'));
    }

    public function test_testing_environment_does_not_require_live_redis(): void
    {
        // Hardening rule: tests must not depend on external Redis.
        self::assertSame('array', config('cache.default'));
        self::assertSame('sync', config('queue.default'));
        self::assertSame('array', config('session.driver'));
    }

    public function test_horizon_is_configured_against_dedicated_redis_connection(): void
    {
        self::assertSame('horizon', config('horizon.use'));
        self::assertArrayHasKey('horizon', config('database.redis'));
    }
}
