<?php

namespace App\Application\Admin\Contracts;

interface PasswordHasherInterface
{
    public function hash(string $plain): string;

    public function verify(string $plain, string $hash): bool;
}
