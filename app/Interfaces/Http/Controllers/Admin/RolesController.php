<?php

namespace App\Interfaces\Http\Controllers\Admin;

use App\Application\Admin\Management\ManageRolesService;
use App\Application\Admin\Services\AdminContext;
use App\Application\Admin\Services\AdminPresenter;
use App\Domain\Admin\Entities\Role;
use App\Domain\Admin\Permissions\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final readonly class RolesController
{
    public function __construct(
        private ManageRolesService $service,
        private AdminContext $context,
        private AdminPresenter $presenter,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => array_map(fn (Role $role): array => $this->presenter->role($role), $this->service->all()),
        ]);
    }

    public function permissions(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ['groups' => Permission::groups(), 'all' => Permission::all()],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->presenter->role($this->service->get($id))]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'slug' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(Permission::all())],
        ]);

        $role = $this->service->create($this->context->require(), $validated);

        return response()->json(['success' => true, 'data' => $this->presenter->role($role)], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::in(Permission::all())],
        ]);

        $role = $this->service->update($this->context->require(), $id, $validated);

        return response()->json(['success' => true, 'data' => $this->presenter->role($role)]);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->service->delete($this->context->require(), $id);

        return response()->json(['success' => true]);
    }
}
