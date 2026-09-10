<?php

namespace Tests\Unit\Infrastructure;

use App\Application\Contracts\ConversationRepositoryInterface;
use App\Application\Contracts\MessageRepositoryInterface;
use App\Application\Contracts\UserRepositoryInterface;
use App\Domain\Conversation\Entities\Conversation;
use App\Domain\Conversation\ValueObjects\ConversationId;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Message\Entities\Message;
use App\Domain\Message\ValueObjects\ExternalMessageId;
use App\Domain\Message\ValueObjects\MessageContent;
use App\Domain\Message\ValueObjects\MessageId;
use App\Domain\Message\ValueObjects\MessagePlatform;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Domain\User\Entities\User;
use App\Domain\User\ValueObjects\UserId;
use App\Infrastructure\Persistence\MongoDB\Mappers\ConversationMapper;
use App\Infrastructure\Persistence\MongoDB\Mappers\MessageMapper;
use App\Infrastructure\Persistence\MongoDB\Mappers\UserMapper;
use App\Infrastructure\Persistence\MongoDB\Repositories\MongoConversationRepository;
use App\Infrastructure\Persistence\MongoDB\Repositories\MongoMessageRepository;
use App\Infrastructure\Persistence\MongoDB\Repositories\MongoUserRepository;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class MongoDBPersistenceTest extends TestCase
{
    public function test_mappers_round_trip_domain_entities(): void
    {
        $tenant = new TenantId('tenant-1');
        $influencer = new InfluencerId('influencer-1');
        $userId = new UserId('user-1');
        $user = new User($userId, $tenant, $influencer, 'telegram', 'external-user', 'amir', 'fa');
        $mappedUser = (new UserMapper)->toDomain((new UserMapper)->toDocument($user));
        self::assertSame('user-1', (string) $mappedUser->id());

        $time = new DateTimeImmutable('2026-09-05T10:00:00+00:00');
        $conversation = Conversation::start(new ConversationId('conversation-1'), $tenant, $influencer, $userId, 'telegram', $time);
        $mappedConversation = (new ConversationMapper)->toDomain((new ConversationMapper)->toDocument($conversation));
        self::assertSame('conversation-1', (string) $mappedConversation->id());
        self::assertSame([], $mappedConversation->releaseDomainEvents());

        $message = Message::create(new MessageId('message-1'), $tenant, $influencer, new ConversationId('conversation-1'), 'user', new MessageContent('hello'), new ExternalMessageId('external-1'), new MessagePlatform('telegram'), $time);
        $mappedMessage = (new MessageMapper)->toDomain((new MessageMapper)->toDocument($message));
        self::assertSame('message-1', (string) $mappedMessage->id());
        self::assertSame([], $mappedMessage->releaseDomainEvents());
    }

    public function test_repositories_implement_application_contracts(): void
    {
        self::assertContains(UserRepositoryInterface::class, class_implements(MongoUserRepository::class));
        self::assertContains(ConversationRepositoryInterface::class, class_implements(MongoConversationRepository::class));
        self::assertContains(MessageRepositoryInterface::class, class_implements(MongoMessageRepository::class));
    }

    public function test_duplicate_message_identity_is_tenant_and_influencer_scoped(): void
    {
        $repository = new class(new MessageMapper) extends MongoMessageRepository
        {
            public function identity(Message $message): array
            {
                return $this->duplicateIdentity($message);
            }
        };
        $message = Message::create(new MessageId('message-1'), new TenantId('tenant-1'), new InfluencerId('influencer-1'), new ConversationId('conversation-1'), 'user', new MessageContent('hello'), new ExternalMessageId('external-1'), new MessagePlatform('telegram'));
        self::assertSame(['tenant_id' => 'tenant-1', 'influencer_id' => 'influencer-1', 'platform' => 'telegram', 'external_message_id' => 'external-1'], $repository->identity($message));
    }
}
