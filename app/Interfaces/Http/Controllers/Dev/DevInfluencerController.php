<?php

namespace App\Interfaces\Http\Controllers\Dev;

use App\Application\Dev\Services\DevSetupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class DevInfluencerController
{
    public function __construct(private DevSetupService $setup) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tenant_id' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'persona' => ['nullable'],
        ]);

        $persona = $validated['persona'] ?? [];
        if (is_string($persona)) {
            $persona = ['description' => $persona, 'persona' => $persona];
        }

        return response()->json([
            'success' => true,
            'data' => $this->setup->createInfluencer(
                (string) $validated['tenant_id'],
                (string) $validated['name'],
                (array) $persona,
            ),
        ], 201);
    }
}
