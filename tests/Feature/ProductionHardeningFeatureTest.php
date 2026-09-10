<?php

namespace Tests\Feature;

use App\Application\Health\HealthCheckService;
use App\Interfaces\Http\Middleware\AssignCorrelationId;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Tests\Unit\Application\FakeHealthCheck;

final class ProductionHardeningFeatureTest extends TestCase
{
    public function test_health_endpoint_reports_dependency_status(): void
    {
        $this->app->instance(HealthCheckService::class, new HealthCheckService([
            new FakeHealthCheck('mongodb', true, 'MongoDB ping succeeded.'),
            new FakeHealthCheck('redis', true, 'Redis ping succeeded.'),
            new FakeHealthCheck('ollama', true, 'Ollama reachable.'),
            new FakeHealthCheck('queue', true, 'Queue connection reachable.'),
        ]));

        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.healthy', true)
            ->assertJsonCount(4, 'data.checks');
    }

    public function test_health_endpoint_returns_503_when_dependency_fails(): void
    {
        $this->app->instance(HealthCheckService::class, new HealthCheckService([
            new FakeHealthCheck('mongodb', false, 'MongoDB unavailable.'),
        ]));

        $this->getJson('/api/v1/health')
            ->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.healthy', false);
    }

    public function test_correlation_id_is_propagated_on_api_responses(): void
    {
        Route::middleware('api')->get('/api/v1/__correlation-probe', function () {
            return response()->json(['ok' => true]);
        });

        $this->withHeader(AssignCorrelationId::HEADER, 'fixed-correlation-123')
            ->getJson('/api/v1/__correlation-probe')
            ->assertOk()
            ->assertHeader(AssignCorrelationId::HEADER, 'fixed-correlation-123');
    }
}
