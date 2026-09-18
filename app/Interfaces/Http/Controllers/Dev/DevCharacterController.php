<?php

namespace App\Interfaces\Http\Controllers\Dev;

use App\Application\Dev\Services\DevSetupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

/**
 * Dev-only AI Character helpers for the test console.
 * Full character settings are edited in the admin panel (`characters` collection).
 */
final readonly class DevCharacterController
{
    public function __construct(private DevSetupService $setup) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->setup->listCharacters(),
        ]);
    }

    /**
     * Sync runtime Persona from admin Character settings so inbound chat works.
     */
    public function prepare(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'character_id' => ['required', 'string'],
        ]);

        try {
            $data = $this->setup->ensurePersonaForCharacter((string) $validated['character_id']);
        } catch (RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 404);
        } catch (Throwable $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }

        return response()->json(['success' => true, 'data' => $data]);
    }
}
