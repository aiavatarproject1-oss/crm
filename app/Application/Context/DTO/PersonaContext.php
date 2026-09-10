<?php

namespace App\Application\Context\DTO;

final readonly class PersonaContext
{
    public function __construct(public ?string $id, public string $name, public string $language, public string $tone, public string $style, public string $description, public array $system_rules, public array $metadata, public bool $fallback = false) {}

    public static function fallback(): self
    {
        return new self(null, '', 'en', 'neutral', 'conversational', '', [], [], true);
    }

    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'language' => $this->language, 'tone' => $this->tone, 'style' => $this->style, 'description' => $this->description, 'system_rules' => $this->system_rules, 'metadata' => $this->metadata, 'fallback' => $this->fallback];
    }
}
