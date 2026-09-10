# Inbound Security (Phase 13.4)

## Problem

Inbound clients previously sent `tenant_id` and `influencer_id` in the JSON body with no authentication. Those values are not trustworthy in production.

## Credential flow

```
X-API-Key header
        ↓
InboundCredentialResolverInterface
        ↓
InboundCredential (tenantId + allowed influencers)
        ↓
Rate limit key = sha256(apiKey) + tenantId
        ↓
Ownership checks
        ↓
InboundMessageController
```

Application contract: `App\Application\Security\Contracts\InboundCredentialResolverInterface`  
Infrastructure: `App\Infrastructure\Security\ConfigInboundCredentialResolver` (config map, no HTTP types)

## Tenant resolution

- Credential → `tenant_id` is authoritative.
- When auth is enabled, body `tenant_id` must equal the credential tenant.
- Mismatch → **403 Forbidden** (no internal detail).

## Influencer validation

Credential config lists allowed influencer ids for that tenant.

- Allowed → continue
- Not listed / empty → **403 Forbidden**
- Optional `["*"]` allows any influencer under the tenant (development only)

## Development mode

```
INBOUND_AUTH_ENABLED=false
```

- No API key required
- Existing Postman body (`tenant_id`, `influencer_id`) continues to work
- Rate limiting still applies using key `disabled|{tenant_id}`

## Production mode

```
INBOUND_AUTH_ENABLED=true
```

- `X-API-Key` required
- Missing/invalid key → **401 Unauthorized**
- Cross-tenant tenant/influencer spoofing → **403 Forbidden**
- Rate limit per credential+tenant → **429** when exceeded

## Configuration

See `config/inbound.php` and `.env.example`:

| Variable | Purpose |
|----------|---------|
| `INBOUND_AUTH_ENABLED` | Master switch (default `false`) |
| `INBOUND_API_KEY_HEADER` | Default `X-API-Key` |
| `INBOUND_RATE_LIMIT_PER_MINUTE` | Default `120` |
| `INBOUND_API_KEY_DEMO` | Local/dev key |
| `INBOUND_API_KEY_DEMO_TENANT` | Tenant bound to demo key |
| `INBOUND_API_KEY_DEMO_INFLUENCERS` | Comma-separated influencer allowlist |

## Middleware order

1. `AuthenticateInboundRequest`
2. `throttle:inbound`
3. `EnsureInboundInfluencerAccess`
