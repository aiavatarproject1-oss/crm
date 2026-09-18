<?php

namespace App\Interfaces\Http\Controllers\Admin;

use App\Application\Admin\Services\AdminContext;
use App\Application\Character\Contracts\CharacterSettingsRepositoryInterface;
use App\Application\Character\DTO\CharacterSettingsData;
use App\Application\Character\Services\ManageCharacterSettingsService;
use App\Application\Quality\Contracts\QualityCheckRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class QualityChecksController
{
    public function __construct(
        private QualityCheckRepositoryInterface $checks,
        private CharacterSettingsRepositoryInterface $characters,
        private ManageCharacterSettingsService $characterSettings,
        private AdminContext $admin,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'character_id' => ['nullable', 'string'],
            'approved' => ['nullable'],
            'search' => ['nullable', 'string', 'max:200'],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 30);
        $result = $this->checks->paginate($page, $perPage, [
            'character_id' => $validated['character_id'] ?? null,
            'approved' => $validated['approved'] ?? null,
            'search' => $validated['search'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data' => array_map(static fn ($row) => $row->toArray(), $result['items']),
            'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $result['total']],
        ]);
    }

    public function settings(): JsonResponse
    {
        $rows = array_map(static function (CharacterSettingsData $c): array {
            return [
                'id' => $c->id,
                'character_id' => $c->characterId,
                'display_name' => $c->displayName,
                'slug' => $c->slug,
                'score_threshold' => $c->qualityScoreThreshold(),
                'version' => $c->version,
            ];
        }, $this->characters->all());

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function updateThreshold(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'character_id' => ['required', 'string'],
            'score_threshold' => ['required', 'numeric', 'min:0', 'max:1'],
        ]);

        $updated = $this->characterSettings->updateSection(
            $this->admin->require(),
            (string) $validated['character_id'],
            'rules_quality',
            ['score_threshold' => round((float) $validated['score_threshold'], 3)],
        );

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $updated->id,
                'character_id' => $updated->characterId,
                'display_name' => $updated->displayName,
                'score_threshold' => $updated->qualityScoreThreshold(),
                'version' => $updated->version,
            ],
        ]);
    }
}
