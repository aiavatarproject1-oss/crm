<?php

namespace App\Interfaces\Http\Controllers\Admin;

use App\Application\Admin\Auth\AdminAuthService;
use App\Application\Admin\Services\AdminContext;
use App\Application\Admin\Services\AdminPresenter;
use App\Application\Admin\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

final readonly class AuthController
{
    public function __construct(
        private AdminAuthService $auth,
        private AdminContext $context,
        private AdminPresenter $presenter,
        private AuditLogger $audit,
    ) {}

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:64'],
            'password' => ['required', 'string', 'max:256'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $this->audit->withRequest($request->ip(), $request->userAgent());

        $result = $this->auth->login(
            (string) $validated['username'],
            (string) $validated['password'],
            (string) ($validated['device_name'] ?? 'admin-panel'),
        );

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $result['token'],
                'token_type' => 'Bearer',
                'expires_at' => $result['expires_at'],
                'admin' => $this->presenter->admin($result['admin']),
            ],
        ]);
    }

    public function me(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->presenter->admin($this->context->require())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $everywhere = $request->boolean('everywhere');
        $this->auth->logout($this->context->require(), $everywhere);

        return response()->json(['success' => true]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'confirmed', Password::min(8)],
        ]);

        $this->auth->changeOwnPassword($this->context->require(), (string) $validated['current_password'], (string) $validated['new_password']);

        return response()->json(['success' => true, 'data' => $this->presenter->admin($this->context->require())]);
    }
}
