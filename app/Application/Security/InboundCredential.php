<?php

namespace App\Application\Security;

/**
 * Authenticated inbound bot context resolved from a credential.
 * Framework-independent DTO (no HTTP types).
 */
final readonly class InboundCredential
{
    /**
     * @param  list<string>  $allowedCharacterIds  Use ['*'] to allow any AI Character under the tenant.
     */
    public function __construct(
        public string $tenantId,
        public array $allowedCharacterIds,
        public string $credentialId,
    ) {}

    public function allowsCharacter(string $characterId): bool
    {
        if (in_array('*', $this->allowedCharacterIds, true)) {
            return true;
        }

        return in_array($characterId, $this->allowedCharacterIds, true);
    }

    /** @deprecated Use allowsCharacter() */
    public function allowsInfluencer(string $influencerId): bool
    {
        return $this->allowsCharacter($influencerId);
    }
}
