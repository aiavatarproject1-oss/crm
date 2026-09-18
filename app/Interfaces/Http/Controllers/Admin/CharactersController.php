<?php

namespace App\Interfaces\Http\Controllers\Admin;

use App\Application\Admin\Services\AdminContext;
use App\Application\Character\Services\ManageCharacterSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final readonly class CharactersController
{
    public function __construct(
        private ManageCharacterSettingsService $service,
        private AdminContext $context,
    ) {}

    public function index(): JsonResponse
    {
        $items = array_map(
            static fn ($c) => [
                'id' => $c->id,
                'tenant_id' => $c->tenantId,
                'character_id' => $c->characterId,
                'slug' => $c->slug,
                'display_name' => $c->displayName,
                'status' => $c->status,
                'version' => $c->version,
                'identity' => [
                    'name' => $c->identity['name'],
                    'age' => $c->identity['age'],
                    'city' => $c->identity['city'],
                    'country' => $c->identity['country'],
                ],
                'updated_at' => $c->updatedAt,
            ],
            $this->service->list(),
        );

        return response()->json(['success' => true, 'data' => $items]);
    }

    public function show(string $id): JsonResponse
    {
        $character = $this->service->get($id);

        return response()->json([
            'success' => true,
            'data' => array_merge($character->toArray(), [
                'prompt_preview' => $character->buildPromptPreview(),
            ]),
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'section' => ['required', 'string', Rule::in([
                'identity', 'behavior', 'identity_defense', 'handoff',
                'sales_funnel', 'rules_quality', 'prompt_studio', 'feature_flags', 'meta',
            ])],
            'data' => ['required', 'array'],
        ]);

        $updated = $this->service->updateSection(
            $this->context->require(),
            $id,
            (string) $validated['section'],
            (array) $validated['data'],
        );

        return response()->json([
            'success' => true,
            'data' => array_merge($updated->toArray(), [
                'prompt_preview' => $updated->buildPromptPreview(),
            ]),
        ]);
    }

    public function promptPreview(string $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ['prompt' => $this->service->promptPreview($id)],
        ]);
    }
}
