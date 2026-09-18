<?php

namespace App\Infrastructure\Persistence\MongoDB\Repositories;

use App\Application\Character\Contracts\CharacterSettingsRepositoryInterface;
use App\Application\Character\DTO\CharacterSettingsData;
use App\Infrastructure\Persistence\MongoDB\Documents\CharacterSettingsDocument;
use DateTimeImmutable;

final class MongoCharacterSettingsRepository implements CharacterSettingsRepositoryInterface
{
    public function all(): array
    {
        return CharacterSettingsDocument::query()
            ->orderBy('display_name')
            ->get()
            ->map(static fn (CharacterSettingsDocument $doc): CharacterSettingsData => self::map($doc))
            ->all();
    }

    public function find(string $id): ?CharacterSettingsData
    {
        $doc = CharacterSettingsDocument::query()->find($id);

        return $doc === null ? null : self::map($doc);
    }

    public function findBySlug(string $slug): ?CharacterSettingsData
    {
        $doc = CharacterSettingsDocument::query()->where('slug', $slug)->first();

        return $doc === null ? null : self::map($doc);
    }

    public function findByCharacter(string $tenantId, string $characterId): ?CharacterSettingsData
    {
        $doc = CharacterSettingsDocument::query()
            ->where('tenant_id', $tenantId)
            ->where('character_id', $characterId)
            ->first();

        return $doc === null ? null : self::map($doc);
    }

    public function save(CharacterSettingsData $settings): void
    {
        $doc = CharacterSettingsDocument::query()->find($settings->id) ?? new CharacterSettingsDocument;
        $payload = $settings->toArray();
        unset($payload['id']);
        $payload['updated_at'] = new DateTimeImmutable;
        if ($doc->exists !== true || $doc->created_at === null) {
            $payload['created_at'] = new DateTimeImmutable($settings->createdAt ?? 'now');
        }
        $doc->forceFill($payload);
        $doc->setAttribute('_id', $settings->id);
        $doc->save();
    }

    public function delete(string $id): void
    {
        CharacterSettingsDocument::query()->where('_id', $id)->first()?->delete();
    }

    private static function map(CharacterSettingsDocument $doc): CharacterSettingsData
    {
        $row = $doc->attributesToArray();
        $row['_id'] = (string) $doc->getAttribute('_id');
        if (isset($row['updated_at']) && ! is_string($row['updated_at'])) {
            $row['updated_at'] = $doc->updated_at?->format(DATE_ATOM);
        }
        if (isset($row['created_at']) && ! is_string($row['created_at'])) {
            $row['created_at'] = $doc->created_at?->format(DATE_ATOM);
        }

        return CharacterSettingsData::fromDocument($row);
    }
}
