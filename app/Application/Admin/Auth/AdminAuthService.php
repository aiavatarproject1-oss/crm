<?php

namespace App\Application\Admin\Auth;

use App\Application\Admin\Contracts\AdminRepositoryInterface;
use App\Application\Admin\Contracts\AdminTokenIssuerInterface;
use App\Application\Admin\Contracts\PasswordHasherInterface;
use App\Application\Admin\Exceptions\AdminAuthException;
use App\Application\Admin\Services\AuditLogger;
use App\Domain\Admin\Entities\Admin;
use App\Domain\Shared\Exceptions\DomainException;

final readonly class AdminAuthService
{
    public function __construct(
        private AdminRepositoryInterface $admins,
        private PasswordHasherInterface $hasher,
        private AdminTokenIssuerInterface $tokens,
        private AuditLogger $audit,
    ) {}

    /**
     * @return array{admin: Admin, token: string, expires_at: ?string}
     */
    public function login(string $username, string $password, string $deviceName = 'admin-panel'): array
    {
        try {
            $username = Admin::normalizeUsername($username);
        } catch (DomainException) {
            throw AdminAuthException::invalidCredentials();
        }

        $admin = $this->admins->findByUsername($username);
        if ($admin === null || ! $this->hasher->verify($password, $admin->passwordHash())) {
            $this->audit->record(null, 'auth.login_failed', 'admin', null, [], ['username' => $username]);

            throw AdminAuthException::invalidCredentials();
        }

        if (! $admin->isActive()) {
            $this->audit->record($admin, 'auth.login_blocked_suspended', 'admin', (string) $admin->id());

            throw AdminAuthException::suspended();
        }

        $admin->recordLogin();
        $this->admins->save($admin);

        $issued = $this->tokens->issue($admin->id(), $deviceName);
        $this->audit->record($admin, 'auth.login', 'admin', (string) $admin->id(), [], ['device' => $deviceName]);

        return ['admin' => $admin, 'token' => $issued['token'], 'expires_at' => $issued['expires_at']];
    }

    public function logout(Admin $admin, bool $everywhere = false): void
    {
        if ($everywhere) {
            $this->tokens->revokeAll($admin->id());
        } else {
            $this->tokens->revokeCurrent();
        }

        $this->audit->record($admin, $everywhere ? 'auth.logout_all' : 'auth.logout', 'admin', (string) $admin->id());
    }

    public function changeOwnPassword(Admin $admin, string $currentPassword, string $newPassword): void
    {
        if (! $this->hasher->verify($currentPassword, $admin->passwordHash())) {
            $this->audit->record($admin, 'auth.password_change_failed', 'admin', (string) $admin->id());

            throw AdminAuthException::wrongCurrentPassword();
        }

        $admin->changePassword($this->hasher->hash($newPassword));
        $this->admins->save($admin);

        // Invalidate every other session; the current token stays valid.
        $this->audit->record($admin, 'auth.password_changed', 'admin', (string) $admin->id());
    }
}
