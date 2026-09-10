<?php

namespace Tests\Feature;

use App\Application\Contracts\AiProcessingTaskRepositoryInterface;
use App\Application\Contracts\ConversationRepositoryInterface;
use App\Application\Contracts\DomainEventPublisherInterface;
use App\Application\Contracts\MessageBatchRepositoryInterface;
use App\Application\Contracts\MessageRepositoryInterface;
use App\Application\Contracts\UserRepositoryInterface;
use App\Jobs\ProcessConversationTurnJob;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;
use Mockery;
use Tests\TestCase;

final class InboundSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('inbound.credentials', [
            'valid-inbound-key' => [
                'tenant_id' => 'tenant-demo',
                'influencers' => ['influencer-sofia'],
            ],
            'other-tenant-key' => [
                'tenant_id' => 'tenant-other',
                'influencers' => ['influencer-other'],
            ],
        ]);
    }

    public function test_valid_api_key_is_accepted_when_auth_enabled(): void
    {
        Config::set('inbound.auth_enabled', true);
        $this->mockInboundPipeline();

        $this->withHeader('X-API-Key', 'valid-inbound-key')
            ->postJson('/api/v1/inbound/messages', $this->telegramBody('tenant-demo', 'influencer-sofia'))
            ->assertCreated()
            ->assertJsonPath('success', true);

        Bus::assertDispatched(ProcessConversationTurnJob::class);
    }

    public function test_invalid_api_key_returns_401(): void
    {
        Config::set('inbound.auth_enabled', true);

        $this->withHeader('X-API-Key', 'wrong-key')
            ->postJson('/api/v1/inbound/messages', $this->telegramBody('tenant-demo', 'influencer-sofia'))
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthorized.');
    }

    public function test_missing_api_key_returns_401_when_auth_enabled(): void
    {
        Config::set('inbound.auth_enabled', true);

        $this->postJson('/api/v1/inbound/messages', $this->telegramBody('tenant-demo', 'influencer-sofia'))
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthorized.');
    }

    public function test_influencer_from_another_tenant_returns_403(): void
    {
        Config::set('inbound.auth_enabled', true);

        $this->withHeader('X-API-Key', 'valid-inbound-key')
            ->postJson('/api/v1/inbound/messages', $this->telegramBody('tenant-demo', 'influencer-other'))
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden.');
    }

    public function test_spoofed_tenant_id_returns_403(): void
    {
        Config::set('inbound.auth_enabled', true);

        $this->withHeader('X-API-Key', 'valid-inbound-key')
            ->postJson('/api/v1/inbound/messages', $this->telegramBody('tenant-other', 'influencer-sofia'))
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden.');
    }

    public function test_disabled_auth_allows_development_request_without_api_key(): void
    {
        Config::set('inbound.auth_enabled', false);
        $this->mockInboundPipeline();

        $this->postJson('/api/v1/inbound/messages', $this->telegramBody('tenant-1', 'influencer-1'))
            ->assertCreated()
            ->assertJsonPath('success', true);

        Bus::assertDispatched(ProcessConversationTurnJob::class);
    }

    public function test_inbound_rate_limit_is_enforced_per_credential_and_tenant(): void
    {
        Config::set('inbound.auth_enabled', true);
        Config::set('inbound.rate_limit_per_minute', 1);
        RateLimiter::clear(hash('sha256', 'valid-inbound-key').'|tenant-demo');
        $this->mockInboundPipeline(times: 1);

        $headers = ['X-API-Key' => 'valid-inbound-key'];
        $body = $this->telegramBody('tenant-demo', 'influencer-sofia', messageId: 501);

        $this->withHeaders($headers)->postJson('/api/v1/inbound/messages', $body)->assertCreated();

        $body['payload']['message']['message_id'] = 502;
        $this->withHeaders($headers)
            ->postJson('/api/v1/inbound/messages', $body)
            ->assertStatus(429);

        Bus::assertDispatched(ProcessConversationTurnJob::class, 1);
    }

    private function mockInboundPipeline(int $times = 1): void
    {
        Bus::fake();

        foreach ([UserRepositoryInterface::class, ConversationRepositoryInterface::class, MessageRepositoryInterface::class, MessageBatchRepositoryInterface::class, DomainEventPublisherInterface::class] as $contract) {
            $this->app->instance($contract, Mockery::mock($contract)->shouldIgnoreMissing());
        }

        $aiTasks = Mockery::mock(AiProcessingTaskRepositoryInterface::class)->shouldIgnoreMissing();
        $aiTasks->shouldReceive('save')->times($times);
        $this->app->instance(AiProcessingTaskRepositoryInterface::class, $aiTasks);

        $this->app->make(MessageRepositoryInterface::class)->shouldReceive('findByExternalIdentity')->times($times)->andReturnNull();
        $this->app->make(UserRepositoryInterface::class)->shouldReceive('findByPlatformIdentity')->times($times * 2)->andReturnNull();
        $this->app->make(ConversationRepositoryInterface::class)->shouldReceive('findActive')->times($times)->andReturnNull();
        $this->app->make(MessageBatchRepositoryInterface::class)->shouldReceive('save')->times($times);
    }

    /** @return array<string, mixed> */
    private function telegramBody(string $tenantId, string $influencerId, int $messageId = 101): array
    {
        return [
            'tenant_id' => $tenantId,
            'influencer_id' => $influencerId,
            'platform' => 'telegram',
            'payload' => [
                'update_id' => 42,
                'message' => [
                    'message_id' => $messageId,
                    'from' => ['id' => 202, 'username' => 'amir'],
                    'text' => 'Hello',
                ],
            ],
        ];
    }
}
