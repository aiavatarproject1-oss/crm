<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Inbound Authentication
    |--------------------------------------------------------------------------
    |
    | When disabled (default), Postman/local development may send tenant_id
    | in the JSON body. When enabled, X-API-Key is required and tenant_id
    | must match the credential's tenant.
    |
    */

    'auth_enabled' => (bool) env('INBOUND_AUTH_ENABLED', false),

    'header' => env('INBOUND_API_KEY_HEADER', 'X-API-Key'),

    'rate_limit_per_minute' => (int) env('INBOUND_RATE_LIMIT_PER_MINUTE', 120),

    /*
    |--------------------------------------------------------------------------
    | API Credentials
    |--------------------------------------------------------------------------
    |
    | Map plaintext API keys to tenant ownership.
    |
    | characters:
    |   - explicit AI Character ids allowed for that tenant
    |   - ["*"] allows any character_id under the tenant (dev only)
    |
    | Legacy env INBOUND_API_KEY_DEMO_INFLUENCERS is still read as a fallback.
    |
    */

    'credentials' => array_filter([
        env('INBOUND_API_KEY_DEMO', 'local-dev-inbound-key') => [
            'tenant_id' => env('INBOUND_API_KEY_DEMO_TENANT', 'tenant-demo'),
            'characters' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env(
                    'INBOUND_API_KEY_DEMO_CHARACTERS',
                    env('INBOUND_API_KEY_DEMO_INFLUENCERS', 'character-estelle'),
                )),
            ))),
        ],
    ], static fn ($definition, $key): bool => is_string($key) && $key !== '' && is_array($definition), ARRAY_FILTER_USE_BOTH),

];
