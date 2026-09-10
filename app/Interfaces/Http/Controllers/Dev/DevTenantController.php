<?php

namespace App\Interfaces\Http\Controllers\Dev;

use App\Application\Dev\Services\DevSetupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class DevTenantController
{
    public function __construct(private DevSetupService $setup) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->setup->createTenant((string) $validated['name']),
        ], 201);
    }
}
