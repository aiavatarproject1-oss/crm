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
use Mockery;
use Tests\TestCase;

final class InboundMessageControllerTest extends TestCase
{
    public function test_it_returns_a_normalized_telegram_message(): void
    {
        Bus::fake();
        $this->mockInboundPersistence();

        $this->postJson('/api/v1/inbound/messages', [
            'tenant_id' => 'tenant-1',
            'influencer_id' => 'influencer-1',
            'platform' => 'telegram',
            'external_user_id' => '202',
            'username' => 'amir',
            'messages' => [
                ['external_message_id' => 'tg-101', 'text' => 'Hello'],
            ],
        ])->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.created', true)
            ->assertJsonPath('data.duplicate', false)
            ->assertJsonPath('data.created_count', 1)
            ->assertJsonPath('data.message_count', 1);

        Bus::assertDispatched(ProcessConversationTurnJob::class);
    }

    public function test_legacy_platform_payload_is_accepted_as_single_item_batch(): void
    {
        Bus::fake();
        $this->mockInboundPersistence();
        $payload = ['update_id' => 42, 'message' => ['message_id' => 101, 'from' => ['id' => 202, 'username' => 'amir'], 'text' => 'Hello']];

        $this->postJson('/api/v1/inbound/messages', ['tenant_id' => 'tenant-1', 'influencer_id' => 'influencer-1', 'platform' => 'telegram', 'payload' => $payload])->assertCreated()->assertJsonPath('success', true)->assertJsonPath('data.created', true)->assertJsonPath('data.duplicate', false);

        Bus::assertDispatched(ProcessConversationTurnJob::class);
    }

    public function test_image_only_message_with_media_is_accepted(): void
    {
        Bus::fake();
        $this->mockInboundPersistence();

        $this->postJson('/api/v1/inbound/messages', [
            'tenant_id' => 'tenant-1',
            'influencer_id' => 'influencer-1',
            'platform' => 'instagram',
            'external_user_id' => 'ig-user-1',
            'messages' => [
                [
                    'external_message_id' => 'mid.img-1',
                    'content_type' => 'image',
                    'media' => [
                        ['url' => 'https://bridge.example/media/a.jpg', 'type' => 'image', 'mime_type' => 'image/jpeg'],
                    ],
                ],
            ],
        ])->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.created_count', 1);

        Bus::assertDispatched(ProcessConversationTurnJob::class);
    }

    public function test_message_without_text_or_media_is_rejected(): void
    {
        $this->postJson('/api/v1/inbound/messages', [
            'tenant_id' => 'tenant-1',
            'influencer_id' => 'influencer-1',
            'platform' => 'instagram',
            'external_user_id' => 'ig-user-1',
            'messages' => [
                ['external_message_id' => 'mid.empty'],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('messages.0');
    }

    public function test_empty_messages_array_is_rejected(): void
    {
        $this->postJson('/api/v1/inbound/messages', [
            'tenant_id' => 'tenant-1',
            'influencer_id' => 'influencer-1',
            'platform' => 'telegram',
            'external_user_id' => '202',
            'messages' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors('messages');
    }

    public function test_unknown_platform_is_rejected(): void
    {
        $this->postJson('/api/v1/inbound/messages', ['tenant_id' => 'tenant-1', 'influencer_id' => 'influencer-1', 'platform' => 'unknown', 'payload' => ['message' => []]])->assertUnprocessable()->assertJsonValidationErrors('platform');
    }

    private function mockInboundPersistence(): void
    {
        foreach ([UserRepositoryInterface::class, ConversationRepositoryInterface::class, MessageRepositoryInterface::class, MessageBatchRepositoryInterface::class, DomainEventPublisherInterface::class] as $contract) {
            $this->app->instance($contract, Mockery::mock($contract)->shouldIgnoreMissing());
        }

        $aiTasks = Mockery::mock(AiProcessingTaskRepositoryInterface::class)->shouldIgnoreMissing();
        $aiTasks->shouldReceive('save')->once();
        $this->app->instance(AiProcessingTaskRepositoryInterface::class, $aiTasks);

        $this->app->make(MessageRepositoryInterface::class)->shouldReceive('findByExternalIdentity')->once()->andReturnNull();
        $this->app->make(UserRepositoryInterface::class)->shouldReceive('findByPlatformIdentity')->twice()->andReturnNull();
        $this->app->make(ConversationRepositoryInterface::class)->shouldReceive('findActive')->once()->andReturnNull();
        $this->app->make(MessageBatchRepositoryInterface::class)->shouldReceive('save')->once();
    }
}
