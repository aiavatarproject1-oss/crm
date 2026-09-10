<?php

namespace App\Infrastructure\Persistence\MongoDB\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Shared Phase 13.1/13.2 persistence helper: Domain ID === MongoDB _id.
 *
 * Create path saves the mapper-built prototype directly (avoids cast round-trip corruption).
 * Update path fills from attributesToArray() so array casts stay PHP arrays.
 */
final class ExplicitIdPersister
{
    /**
     * @param  callable(): ?Model  $findAlternate  Optional natural-key fallback that must not invent a new _id.
     */
    public static function save(Model $prototype, string $id, ?callable $findAlternate = null): void
    {
        $existing = $prototype->newQuery()->find($id);
        if ($existing === null && $findAlternate !== null) {
            $existing = $findAlternate();
        }

        if ($existing !== null) {
            $payload = $prototype->attributesToArray();
            unset($payload['_id'], $payload['id']);
            $existing->fill($payload);
            $existing->save();

            return;
        }

        $prototype->setAttribute('_id', $id);
        $prototype->save();
    }
}
