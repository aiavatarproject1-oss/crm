<?php

namespace App\Application\Admin\Services;

use App\Domain\Admin\Entities\Admin;
use RuntimeException;

/**
 * Request-scoped holder for the authenticated admin (set by middleware).
 */
final class AdminContext
{
    private ?Admin $admin = null;

    public function set(Admin $admin): void
    {
        $this->admin = $admin;
    }

    public function get(): ?Admin
    {
        return $this->admin;
    }

    public function require(): Admin
    {
        return $this->admin ?? throw new RuntimeException('No authenticated admin in context.');
    }

    public function clear(): void
    {
        $this->admin = null;
    }
}
