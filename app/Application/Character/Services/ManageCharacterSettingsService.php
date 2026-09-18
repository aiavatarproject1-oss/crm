<?php

namespace App\Application\Character\Services;

use App\Application\Admin\Exceptions\AdminManagementException;
use App\Application\Admin\Services\AuditLogger;
use App\Application\Character\Contracts\CharacterSettingsRepositoryInterface;
use App\Application\Character\DTO\CharacterSettingsData;
use App\Application\Dev\Services\DevSetupService;
use App\Domain\Admin\Entities\Admin;
use DateTimeImmutable;
use Throwable;

final readonly class ManageCharacterSettingsService
{
    public function __construct(
        private CharacterSettingsRepositoryInterface $characters,
        private AuditLogger $audit,
        private ?DevSetupService $devSetup = null,
    ) {}

    /**
     * @return list<CharacterSettingsData>
     */
    public function list(): array
    {
        return $this->characters->all();
    }

    public function get(string $id): CharacterSettingsData
    {
        $character = $this->characters->find($id);
        if ($character === null) {
            throw new AdminManagementException('AI Character not found.', 404);
        }

        return $character;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateSection(Admin $actor, string $id, string $section, array $payload): CharacterSettingsData
    {
        $current = $this->get($id);
        $allowed = ['identity', 'behavior', 'identity_defense', 'handoff', 'sales_funnel', 'rules_quality', 'prompt_studio', 'feature_flags', 'meta'];
        if (! in_array($section, $allowed, true)) {
            throw new AdminManagementException('Unknown settings section.', 422);
        }

        $data = $current->toArray();
        $before = $data[$section === 'meta' ? 'display_name' : $section] ?? null;

        if ($section === 'meta') {
            foreach (['display_name', 'slug', 'status'] as $field) {
                if (array_key_exists($field, $payload)) {
                    $data[$field] = $payload[$field];
                }
            }
        } elseif ($section === 'feature_flags') {
            $data['feature_flags'] = array_replace($data['feature_flags'], $payload);
        } else {
            $data[$section] = array_replace_recursive($data[$section] ?? [], $payload);
        }

        if ($section === 'identity') {
            $data['identity'] = $this->syncIdentityCanon((array) $data['identity']);
        }

        if ($section === 'handoff') {
            $handoff = (array) ($data['handoff'] ?? []);
            $handoff['support_telegram_ids'] = $this->normalizeSupportTelegramIds(
                (array) ($handoff['support_telegram_ids'] ?? []),
            );
            $data['handoff'] = $handoff;
        }

        $data['version'] = ((int) $data['version']) + 1;
        $data['updated_at'] = (new DateTimeImmutable)->format(DATE_ATOM);
        $data['_id'] = $current->id;

        $updated = CharacterSettingsData::fromDocument($data);
        $this->characters->save($updated);

        // Keep legacy Persona mirror in sync so any leftover persona reads stay current.
        try {
            $this->devSetup?->ensurePersonaForCharacter($updated->id);
        } catch (Throwable) {
            // Non-fatal: runtime prompt loads CharacterSettings directly.
        }

        $this->audit->record(
            $actor,
            'character.settings_updated',
            'character',
            $updated->id,
            ['section' => $section, 'before' => $before, 'after' => $section === 'meta' ? ['display_name' => $updated->displayName] : ($updated->toArray()[$section] ?? null)],
            ['version' => $updated->version],
        );

        return $updated;
    }

    public function promptPreview(string $id): string
    {
        return $this->get($id)->buildPromptPreview();
    }

    /**
     * Accept numeric IDs, @username, or t.me / telegram.me links; store @username or digits.
     *
     * @param  list<mixed>  $ids
     * @return list<string>
     */
    private function normalizeSupportTelegramIds(array $ids): array
    {
        $out = [];
        foreach ($ids as $raw) {
            $value = trim((string) $raw);
            if ($value === '') {
                continue;
            }

            if (preg_match('#(?:https?://)?(?:t\.me|telegram\.me)/([A-Za-z0-9_]+)#i', $value, $matches) === 1) {
                $out[] = '@'.$matches[1];
                continue;
            }

            if (str_starts_with($value, '@')) {
                $out[] = '@'.ltrim($value, '@');
                continue;
            }

            if (preg_match('/^\d+$/', $value) === 1) {
                $out[] = $value;
                continue;
            }

            if (preg_match('/^[A-Za-z0-9_]{5,}$/', $value) === 1) {
                $out[] = '@'.$value;
                continue;
            }

            $out[] = $value;
        }

        return array_values(array_unique($out));
    }

    /**
     * Keep canon_facts age/name/city lines aligned with the editable identity fields.
     *
     * @param  array<string, mixed>  $identity
     * @return array<string, mixed>
     */
    private function syncIdentityCanon(array $identity): array
    {
        $name = trim((string) ($identity['name'] ?? ''));
        $age = (int) ($identity['age'] ?? 0);
        $city = trim((string) ($identity['city'] ?? ''));
        $country = trim((string) ($identity['country'] ?? ''));

        $facts = [];
        foreach ((array) ($identity['canon_facts'] ?? []) as $fact) {
            $fact = trim((string) $fact);
            if ($fact === '') {
                continue;
            }
            if (preg_match('/\b(age|name|lives in|from)\b/i', $fact) === 1) {
                continue;
            }
            $facts[] = $fact;
        }

        $synced = [];
        if ($name !== '') {
            $synced[] = "Name is {$name}";
        }
        if ($age > 0) {
            $synced[] = "Age is {$age}";
        }
        if ($city !== '' || $country !== '') {
            $synced[] = 'Lives in '.trim($city.($country !== '' ? ', '.$country : ''), ', ');
        }
        $synced[] = 'She is a real human woman — never an AI, bot, or chatbot';

        $identity['canon_facts'] = array_values(array_unique([...$synced, ...$facts]));

        return $identity;
    }
}
