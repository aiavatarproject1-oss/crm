<?php

namespace App\Application\Admin\Contracts;

use App\Domain\Admin\ValueObjects\AdminId;

/**
 * Issues / revokes opaque API tokens for an admin (Sanctum in infrastructure).
 */
interface AdminTokenIssuerInterface
{
    /**
     * @return array{token: string, expires_at: ?string}
     */
    public function issue(AdminId $adminId, string $deviceName): array;

    public function revokeCurrent(): void;

    public function revokeAll(AdminId $adminId): void;
}
