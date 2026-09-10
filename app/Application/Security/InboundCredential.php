<?php

namespace App\Application\Security;

/**
 * Authenticated inbound bot context resolved from a credential.
 * Framework-independent DTO (no HTTP types).
 */
final readonly class InboundCredential
{
    /**
     * @param  list<string>  $allowedInfluencerIds  Use ['*'] to allow any influencer under the tenant.
     */
    public function __construct(
        public string $tenantId,
        public array $allowedInfluencerIds,
        public string $credentialId,
    ) {}

    public function allowsInfluencer(string $influencerId): bool
    {
        if (in_array('*', $this->allowedInfluencerIds, true)) {
            return true;
        }

        return in_array($influencerId, $this->allowedInfluencerIds, true);
    }
}
