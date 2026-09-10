<?php

namespace App\Infrastructure\Persistence\MongoDB\Repositories;

use App\Application\Contracts\UserRepositoryInterface;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Domain\User\Entities\User;
use App\Domain\User\ValueObjects\UserId;
use App\Infrastructure\Persistence\MongoDB\Documents\UserDocument;
use App\Infrastructure\Persistence\MongoDB\Mappers\UserMapper;
use App\Infrastructure\Persistence\MongoDB\Support\ExplicitIdPersister;

final class MongoUserRepository implements UserRepositoryInterface
{
    public function __construct(private readonly UserMapper $mapper) {}

    public function find(UserId $userId): ?User
    {
        $document = UserDocument::query()->find((string) $userId);

        return $document === null ? null : $this->mapper->toDomain($document);
    }

    public function findByPlatformIdentity(TenantId $tenantId, string $platform, string $externalUserId): ?User
    {
        $document = UserDocument::query()
            ->where('tenant_id', (string) $tenantId)
            ->where('platform', $platform)
            ->where('platform_user_id', $externalUserId)
            ->first();

        return $document === null ? null : $this->mapper->toDomain($document);
    }

    public function save(User $user): void
    {
        ExplicitIdPersister::save(
            $this->mapper->toDocument($user),
            (string) $user->id(),
            static fn () => UserDocument::query()
                ->where('tenant_id', (string) $user->tenantId)
                ->where('platform', $user->platform)
                ->where('platform_user_id', $user->externalUserId)
                ->first(),
        );
    }
}
