<?php

namespace App\Infrastructure\Security;

use App\Application\Admin\Contracts\AdminTokenIssuerInterface;
use App\Domain\Admin\ValueObjects\AdminId;
use App\Infrastructure\Persistence\MongoDB\Documents\AdminDocument;
use App\Infrastructure\Persistence\MongoDB\Documents\PersonalAccessTokenDocument;
use DateTimeInterface;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Laravel\Sanctum\PersonalAccessToken;

final readonly class SanctumAdminTokenIssuer implements AdminTokenIssuerInterface
{
    public function __construct(private AuthFactory $auth) {}

    public function issue(AdminId $adminId, string $deviceName): array
    {
        $document = AdminDocument::query()->findOrFail((string) $adminId);

        $minutes = config('sanctum.expiration');
        $expiresAt = is_numeric($minutes) && (int) $minutes > 0 ? now()->addMinutes((int) $minutes) : null;

        $token = $document->createToken(mb_substr($deviceName, 0, 100), ['admin'], $expiresAt);

        return [
            'token' => $token->plainTextToken,
            'expires_at' => $expiresAt?->format(DateTimeInterface::ATOM),
        ];
    }

    public function revokeCurrent(): void
    {
        $user = $this->auth->guard('admin')->user();
        if (! $user instanceof AdminDocument) {
            return;
        }

        $token = $user->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }
    }

    public function revokeAll(AdminId $adminId): void
    {
        PersonalAccessTokenDocument::query()
            ->where('tokenable_type', AdminDocument::class)
            ->where('tokenable_id', (string) $adminId)
            ->delete();
    }
}
