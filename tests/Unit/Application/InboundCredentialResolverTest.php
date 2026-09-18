<?php

namespace Tests\Unit\Application;

use App\Application\Security\InboundCredential;
use App\Infrastructure\Security\ConfigInboundCredentialResolver;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

final class InboundCredentialResolverTest extends TestCase
{
    public function test_resolver_maps_api_key_to_tenant_without_http_types(): void
    {
        Config::set('inbound.credentials', [
            'key-a' => [
                'tenant_id' => 'tenant-a',
                'characters' => ['char-1'],
            ],
        ]);

        $credential = (new ConfigInboundCredentialResolver)->resolve('key-a');

        self::assertInstanceOf(InboundCredential::class, $credential);
        self::assertSame('tenant-a', $credential->tenantId);
        self::assertTrue($credential->allowsCharacter('char-1'));
        self::assertFalse($credential->allowsCharacter('char-2'));
    }

    public function test_unknown_api_key_returns_null(): void
    {
        Config::set('inbound.credentials', []);
        self::assertNull((new ConfigInboundCredentialResolver)->resolve('missing'));
        self::assertNull((new ConfigInboundCredentialResolver)->resolve(''));
    }
}
