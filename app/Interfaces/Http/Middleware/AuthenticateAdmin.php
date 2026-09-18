<?php

namespace App\Interfaces\Http\Middleware;

use App\Application\Admin\Contracts\AdminRepositoryInterface;
use App\Application\Admin\Services\AdminContext;
use App\Application\Admin\Services\AuditLogger;
use App\Domain\Admin\ValueObjects\AdminId;
use App\Infrastructure\Persistence\MongoDB\Documents\AdminDocument;
use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the Sanctum bearer token to a domain Admin, rejects suspended accounts,
 * and primes AdminContext + AuditLogger with request metadata.
 */
final class AuthenticateAdmin
{
    public function __construct(
        private readonly AuthFactory $auth,
        private readonly AdminRepositoryInterface $admins,
        private readonly AdminContext $context,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->auth->guard('admin')->user();
        if (! $user instanceof AdminDocument) {
            return $this->unauthenticated();
        }

        $admin = $this->admins->find(new AdminId((string) $user->getAttribute('_id')));
        if ($admin === null) {
            return $this->unauthenticated();
        }

        if (! $admin->isActive()) {
            return response()->json(['success' => false, 'message' => 'This account is suspended.', 'code' => 'suspended'], 403);
        }

        $this->context->set($admin);
        $this->audit->withRequest($request->ip(), $request->userAgent());
        $request->attributes->set('admin', $admin);

        return $next($request);
    }

    private function unauthenticated(): Response
    {
        return response()->json(['success' => false, 'message' => 'Unauthenticated.', 'code' => 'unauthenticated'], 401);
    }
}
