<?php

namespace App\Application\Character\Contracts;

use App\Application\Character\DTO\CharacterSettingsData;

interface CharacterSettingsRepositoryInterface
{
    /**
     * @return list<CharacterSettingsData>
     */
    public function all(): array;

    public function find(string $id): ?CharacterSettingsData;

    public function findBySlug(string $slug): ?CharacterSettingsData;

    public function findByCharacter(string $tenantId, string $characterId): ?CharacterSettingsData;

    public function save(CharacterSettingsData $settings): void;

    public function delete(string $id): void;
}
