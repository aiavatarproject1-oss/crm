<?php

namespace App\Application\Observability\Contracts;

interface StructuredLoggerInterface
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function info(string $event, array $context = []): void;

    /**
     * @param  array<string, mixed>  $context
     */
    public function warning(string $event, array $context = []): void;

    /**
     * @param  array<string, mixed>  $context
     */
    public function error(string $event, array $context = []): void;
}
