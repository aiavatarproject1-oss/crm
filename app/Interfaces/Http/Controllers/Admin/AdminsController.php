<?php

namespace App\Interfaces\Http\Controllers\Admin;

use App\Application\Admin\Management\ManageAdminsService;
use App\Application\Admin\Services\AdminContext;
use App\Application\Admin\Services\AdminPresenter;
use App\Domain\Admin\Entities\Admin;
use App\Domain\Admin\Permissions\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final readonly class AdminsController
{
    public function __construct(
        private ManageAdminsService $service,
        private AdminContext $context,
        private AdminPresenter $presenter,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in([Admin::STATUS_ACTIVE, Admin::STATUS_SUSPENDED])],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 20);
        $result = $this->service->list($page, $perPage, $validated['search'] ?? null, $validated['status'] ?? null);

        return response()->json([
            'success' => true,
            'data' => array_map(fn (Admin $admin): array => $this->presenter->admin($admin, withPermissions: false), $result['items']),
            'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $result['total']],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->presenter->admin($this->service->get($id))]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:32', 'regex:/^[a-zA-Z0-9._-]+$/'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190'],
            'password' => ['required', 'string', Password::min(8)],
            'is_super_admin' => ['nullable', 'boolean'],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['string'],
            'direct_permissions' => ['nullable', 'array'],
            'direct_permissions.*' => ['string', Rule::in(Permission::all())],
            'locale' => ['nullable', 'string', 'max:8'],
            'must_change_password' => ['nullable', 'boolean'],
        ]);

        $admin = $this->service->create($this->context->require(), $validated);

        return response()->json(['success' => true, 'data' => $this->presenter->admin($admin)], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'email' => ['sometimes', 'nullable', 'email', 'max:190'],
            'locale' => ['sometimes', 'string', 'max:8'],
            'is_super_admin' => ['sometimes', 'boolean'],
            'role_ids' => ['sometimes', 'array'],
            'role_ids.*' => ['string'],
            'direct_permissions' => ['sometimes', 'array'],
            'direct_permissions.*' => ['string', Rule::in(Permission::all())],
            'status' => ['sometimes', Rule::in([Admin::STATUS_ACTIVE, Admin::STATUS_SUSPENDED])],
        ]);

        $admin = $this->service->update($this->context->require(), $id, $validated);

        return response()->json(['success' => true, 'data' => $this->presenter->admin($admin)]);
    }

    public function resetPassword(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'new_password' => ['required', 'string', Password::min(8)],
            'must_change_password' => ['nullable', 'boolean'],
        ]);

        $admin = $this->service->resetPassword(
            $this->context->require(),
            $id,
            (string) $validated['new_password'],
            (bool) ($validated['must_change_password'] ?? true),
        );

        return response()->json(['success' => true, 'data' => $this->presenter->admin($admin)]);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->service->delete($this->context->require(), $id);

        return response()->json(['success' => true]);
    }
}
