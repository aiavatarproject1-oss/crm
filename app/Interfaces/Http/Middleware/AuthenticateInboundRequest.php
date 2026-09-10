<?php

namespace App\Interfaces\Http\Middleware;

use App\Application\Security\Contracts\InboundCredentialResolverInterface;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateInboundRequest
{
    public function __construct(private readonly InboundCredentialResolverInterface $credentials) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('inbound.auth_enabled', false)) {
            $tenantId = (string) $request->input('tenant_id', 'anonymous');
            $request->attributes->set('inbound_auth_enabled', false);
            $request->attributes->set('inbound_rate_key', 'disabled|'.$tenantId);

            return $next($request);
        }

        $header = (string) config('inbound.header', 'X-API-Key');
        $apiKey = trim((string) $request->headers->get($header, ''));
        $credential = $this->credentials->resolve($apiKey);

        if ($credential === null) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        $request->attributes->set('inbound_auth_enabled', true);
        $request->attributes->set('inbound_credential', $credential);
        $request->attributes->set('inbound_tenant_id', $credential->tenantId);
        $request->attributes->set('inbound_rate_key', $credential->credentialId.'|'.$credential->tenantId);

        return $next($request);
    }
}
