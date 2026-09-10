<?php

namespace App\Application\Security\Contracts;

use App\Application\Security\InboundCredential;

interface InboundCredentialResolverInterface
{
    /**
     * Resolve a raw API credential into tenant context.
     * Returns null when the credential is unknown or empty.
     */
    public function resolve(string $apiKey): ?InboundCredential;
}
