<?php

namespace App\Interfaces\Http\Controllers\Dev;

use App\Application\AI\Services\PromptPolicyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class DevPromptPolicyController
{
    public function __construct(private PromptPolicyService $policies) {}

    public function show(Request $request): JsonResponse
    {
        $tenantId = $request->query('tenant_id');
        $policy = $this->policies->resolve(is_string($tenantId) ? $tenantId : null);

        return response()->json([
            'success' => true,
            'data' => $policy->toArray(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'role_intro' => ['required', 'string', 'max:2000'],
            'style_lines' => ['required', 'array', 'min:1'],
            'style_lines.*' => ['string', 'max:500'],
            'stage_lines' => ['required', 'array'],
            'stage_lines.warmup' => ['required', 'array', 'min:1'],
            'stage_lines.warmup.*' => ['string', 'max:500'],
            'stage_lines.tease' => ['required', 'array', 'min:1'],
            'stage_lines.tease.*' => ['string', 'max:500'],
            'stage_lines.sell' => ['required', 'array', 'min:1'],
            'stage_lines.sell.*' => ['string', 'max:500'],
            'closing_line' => ['required', 'string', 'max:1000'],
        ]);

        $policy = $this->policies->upsertGlobal($validated);

        return response()->json([
            'success' => true,
            'data' => $policy->toArray(),
        ]);
    }
}
