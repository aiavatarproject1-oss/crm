<?php

namespace App\Interfaces\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class InboundMediaController
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:jpeg,jpg,png,webp,gif'],
            'tenant_id' => ['nullable', 'string', 'max:120'],
            'character_id' => ['nullable', 'string', 'max:120'],
            'influencer_id' => ['nullable', 'string', 'max:120'],
        ]);

        $file = $request->file('file');
        if ($file === null || ! $file->isValid()) {
            throw ValidationException::withMessages(['file' => ['Invalid upload.']]);
        }

        $tenantId = (string) ($request->attributes->get('inbound_tenant_id')
            ?: ($validated['tenant_id'] ?? 'anonymous'));
        $characterId = (string) ($validated['character_id']
            ?? $validated['influencer_id']
            ?? 'general');

        $ext = strtolower((string) ($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin'));
        $safeExt = preg_replace('/[^a-z0-9]/', '', $ext) ?: 'bin';
        $relative = sprintf(
            'inbound-media/%s/%s/%s/%s.%s',
            Str::slug($tenantId) ?: 'tenant',
            Str::slug($characterId) ?: 'character',
            now()->format('Y/m/d'),
            (string) Str::ulid(),
            $safeExt,
        );

        Storage::disk('public')->putFileAs(
            dirname($relative),
            $file,
            basename($relative),
        );

        $publicPath = 'storage/'.$relative;
        $url = $this->publicUrl($request, $publicPath);

        return response()->json([
            'success' => true,
            'data' => [
                'url' => $url,
                'path' => $relative,
                'disk' => 'public',
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ],
        ], 201);
    }

    private function publicUrl(Request $request, string $publicPath): string
    {
        $appUrl = rtrim((string) config('app.url', ''), '/');
        $host = rtrim($request->getSchemeAndHttpHost(), '/');

        if (str_starts_with($appUrl, 'https://')) {
            $base = $appUrl;
        } elseif (str_contains($host, 'ngrok') && str_starts_with($host, 'http://')) {
            $base = 'https://'.preg_replace('#^https?://#', '', $host);
        } else {
            $base = $host;
        }

        return $base.'/'.ltrim($publicPath, '/');
    }
}
