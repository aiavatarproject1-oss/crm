<?php

namespace App\Application\Admin\Exceptions;

use RuntimeException;

final class AdminManagementException extends RuntimeException
{
    public static function notFound(string $what = 'Admin'): self
    {
        return new self($what.' not found.', 404);
    }

    public static function usernameTaken(string $username): self
    {
        return new self("Username '{$username}' is already taken.", 422);
    }

    public static function slugTaken(string $slug): self
    {
        return new self("Role '{$slug}' already exists.", 422);
    }

    public static function lastSuperAdmin(): self
    {
        return new self('At least one super admin must remain.', 422);
    }

    public static function cannotModifySelfAccess(): self
    {
        return new self('You cannot change your own roles, permissions or status.', 422);
    }

    public static function systemRole(): self
    {
        return new self('System roles cannot be deleted.', 422);
    }

    public static function unknownRole(string $roleId): self
    {
        return new self("Unknown role '{$roleId}'.", 422);
    }
}
