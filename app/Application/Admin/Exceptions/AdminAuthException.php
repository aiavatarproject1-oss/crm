<?php

namespace App\Application\Admin\Exceptions;

use RuntimeException;

final class AdminAuthException extends RuntimeException
{
    public static function invalidCredentials(): self
    {
        return new self('Invalid username or password.', 401);
    }

    public static function suspended(): self
    {
        return new self('This account is suspended.', 403);
    }

    public static function wrongCurrentPassword(): self
    {
        return new self('Current password is incorrect.', 422);
    }
}
