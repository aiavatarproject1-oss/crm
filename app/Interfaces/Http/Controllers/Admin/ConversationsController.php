<?php

namespace App\Interfaces\Http\Controllers\Admin;

use App\Application\Admin\Contracts\ConversationReadModelInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final readonly class ConversationsController
{
    public function __construct(private ConversationReadModelInterface $conversations) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'tenant_id' => ['nullable', 'string'],
            'influencer_id' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(['active', 'paused', 'closed'])],
            'platform' => ['nullable', 'string', 'max:32'],
            'search' => ['nullable', 'string', 'max:100'],
            'active_within_minutes' => ['nullable', 'integer', 'min:1', 'max:10080'],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 20);
        $filters = $validated;
        if (isset($filters['active_within_minutes'])) {
            $filters['active_within_minutes'] = (int) $filters['active_within_minutes'];
        }
        $result = $this->conversations->paginate($page, $perPage, $filters);

        return response()->json([
            'success' => true,
            'data' => $result['items'],
            'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $result['total']],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $conversation = $this->conversations->find($id);
        if ($conversation === null) {
            return response()->json(['success' => false, 'message' => 'Conversation not found.', 'code' => 'not_found'], 404);
        }

        return response()->json(['success' => true, 'data' => $conversation]);
    }

    public function messages(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        if ($this->conversations->find($id) === null) {
            return response()->json(['success' => false, 'message' => 'Conversation not found.', 'code' => 'not_found'], 404);
        }

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 50);
        $result = $this->conversations->messages($id, $page, $perPage);

        return response()->json([
            'success' => true,
            'data' => $result['items'],
            'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $result['total']],
        ]);
    }
}
