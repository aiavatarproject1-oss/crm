<?php

namespace Tests\Feature;

use App\Application\Exceptions\ApplicationException;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

final class ApiExceptionHardeningTest extends TestCase
{
    public function test_application_exception_returns_safe_json_without_provider_details(): void
    {
        Route::get('/api/v1/__exception-hardening', function () {
            throw new ApplicationException(
                'LLM response generation failed.',
                0,
                new RuntimeException('secret provider stack / api-key=sk-test'),
            );
        });

        $response = $this->getJson('/api/v1/__exception-hardening');

        $response->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'LLM response generation failed.')
            ->assertJsonMissingPath('exception')
            ->assertJsonMissingPath('trace')
            ->assertJsonMissingPath('file')
            ->assertJsonMissingPath('line');

        self::assertStringNotContainsString('secret provider stack', $response->getContent());
        self::assertStringNotContainsString('sk-test', $response->getContent());
    }
}
