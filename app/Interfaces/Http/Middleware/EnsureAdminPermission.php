<?php

namespace App\Interfaces\Http\Middleware;

use App\Application\Admin\Services\AdminAccessResolver;
use App\Application\Admin\Services\AdminContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('permission:admins.manage') or 'permission:a|b' (any of).
 */
final class EnsureAdminPermission
{
    public function __construct(
        private readonly AdminContext $context,
        private readonly AdminAccessResolver $access,
    ) {}

    public function handle(Request $request, Closure $next, string $permissions): Response
    {
        $admin = $this->context->get();
        if ($admin === null) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.', 'code' => 'unauthenticated'], 401);
        }

        foreach (explode('|', $permissions) as $permission) {
            if ($this->access->can($admin, trim($permission))) {
                return $next($request);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'You do not have permission to perform this action.',
            'code' => 'forbidden',
            'required' => explode('|', $permissions),
        ], 403);
    }
}
