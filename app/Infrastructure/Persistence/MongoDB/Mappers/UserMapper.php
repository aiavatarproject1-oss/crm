<?php

namespace App\Infrastructure\Persistence\MongoDB\Mappers;

use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Domain\User\Entities\User;
use App\Domain\User\ValueObjects\UserId;
use App\Infrastructure\Persistence\MongoDB\Documents\UserDocument;

final class UserMapper
{
    public function toDocument(User $user): UserDocument
    {
        $document = new UserDocument([
            'tenant_id' => (string) $user->tenantId,
            'influencer_id' => (string) $user->influencerId,
            'platform' => $user->platform,
            'platform_user_id' => $user->externalUserId,
            'username' => $user->username,
            'language' => $user->language,
        ]);
        $document->setAttribute('_id', (string) $user->id());

        return $document;
    }

    public function toDomain(UserDocument $document): User
    {
        return new User(
            new UserId((string) $document->getAttribute('_id')),
            new TenantId((string) $document->tenant_id),
            new InfluencerId((string) $document->influencer_id),
            (string) $document->platform,
            (string) $document->platform_user_id,
            $document->username,
            $document->language,
        );
    }
}
