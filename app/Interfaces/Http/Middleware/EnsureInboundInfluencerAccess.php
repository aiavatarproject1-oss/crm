<?php

namespace App\Interfaces\Http\Middleware;

use App\Application\Security\InboundCredential;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures body tenant/influencer ownership matches the authenticated credential.
 */
final class EnsureInboundInfluencerAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) $request->attributes->get('inbound_auth_enabled', false)) {
            return $next($request);
        }

        /** @var InboundCredential|null $credential */
        $credential = $request->attributes->get('inbound_credential');
        if (! $credential instanceof InboundCredential) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        $requestTenantId = trim((string) $request->input('tenant_id', ''));
        $influencerId = trim((string) $request->input('influencer_id', ''));

        if ($requestTenantId === '' || $requestTenantId !== $credential->tenantId) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        if ($influencerId === '' || ! $credential->allowsInfluencer($influencerId)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        return $next($request);
    }
}
