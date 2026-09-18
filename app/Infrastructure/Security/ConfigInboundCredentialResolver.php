<?php

namespace App\Infrastructure\Security;

use App\Application\Security\Contracts\InboundCredentialResolverInterface;
use App\Application\Security\InboundCredential;

/**
 * Config-backed inbound API key → tenant mapping.
 * No Laravel Request dependency.
 */
final class ConfigInboundCredentialResolver implements InboundCredentialResolverInterface
{
    public function resolve(string $apiKey): ?InboundCredential
    {
        $apiKey = trim($apiKey);
        if ($apiKey === '') {
            return null;
        }

        /** @var array<string, array{tenant_id?: string, characters?: list<string>, influencers?: list<string>}> $credentials */
        $credentials = (array) config('inbound.credentials', []);
        if (! array_key_exists($apiKey, $credentials)) {
            return null;
        }

        $definition = $credentials[$apiKey];
        $tenantId = trim((string) ($definition['tenant_id'] ?? ''));
        if ($tenantId === '') {
            return null;
        }

        $characters = array_values(array_filter(
            array_map('strval', (array) ($definition['characters'] ?? $definition['influencers'] ?? [])),
            static fn (string $id): bool => $id !== '',
        ));

        return new InboundCredential($tenantId, $characters, credentialId: hash('sha256', $apiKey));
    }
}
