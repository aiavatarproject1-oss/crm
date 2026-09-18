<?php

namespace App\Infrastructure\Security;

use App\Application\Admin\Contracts\PasswordHasherInterface;
use Illuminate\Contracts\Hashing\Hasher;

final readonly class BcryptPasswordHasher implements PasswordHasherInterface
{
    public function __construct(private Hasher $hasher) {}

    public function hash(string $plain): string
    {
        return $this->hasher->make($plain);
    }

    public function verify(string $plain, string $hash): bool
    {
        return $hash !== '' && $this->hasher->check($plain, $hash);
    }
}
