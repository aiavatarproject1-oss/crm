<?php

namespace Tests\Unit\Application;

use App\Application\Context\BuildPersonaContextHandler;
use App\Application\Contracts\PersonaRepositoryInterface;
use App\Domain\Influencer\Entities\Persona;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Influencer\ValueObjects\PersonaId;
use App\Domain\Tenant\ValueObjects\TenantId;
use PHPUnit\Framework\TestCase;

final class PersonaContextTest extends TestCase
{
    public function test_influencer_persona_loads_correctly(): void
    {
        $context = $this->handler([$this->persona('tenant-1', 'influencer-1', 'Sofia')])->handle(new TenantId('tenant-1'), new InfluencerId('influencer-1'));
        self::assertSame('Sofia', $context->name);
        self::assertSame('warm', $context->tone);
        self::assertFalse($context->fallback);
    }

    public function test_persona_is_tenant_isolated(): void
    {
        $handler = $this->handler([$this->persona('tenant-2', 'influencer-1', 'Foreign')]);
        self::assertTrue($handler->handle(new TenantId('tenant-1'), new InfluencerId('influencer-1'))->fallback);
    }

    public function test_different_influencers_have_different_personas(): void
    {
        $handler = $this->handler([$this->persona('tenant-1', 'influencer-1', 'Sofia'), $this->persona('tenant-1', 'influencer-2', 'Estelle')]);
        self::assertSame('Sofia', $handler->handle(new TenantId('tenant-1'), new InfluencerId('influencer-1'))->name);
        self::assertSame('Estelle', $handler->handle(new TenantId('tenant-1'), new InfluencerId('influencer-2'))->name);
    }

    public function test_missing_persona_returns_safe_fallback(): void
    {
        $context = $this->handler([])->handle(new TenantId('tenant-1'), new InfluencerId('influencer-1'));
        self::assertTrue($context->fallback);
        self::assertSame('en', $context->language);
        self::assertSame([], $context->system_rules);
    }

    private function handler(array $personas): BuildPersonaContextHandler
    {
        return new BuildPersonaContextHandler(new ScopedPersonas($personas));
    }

    private function persona(string $tenant, string $influencer, string $name): Persona
    {
        return new Persona(new PersonaId('persona-'.$name), new TenantId($tenant), new InfluencerId($influencer), $name, 'en', 'warm', 'friendly', "$name description", ['Be respectful'], ['source' => 'test']);
    }
}

final readonly class ScopedPersonas implements PersonaRepositoryInterface
{
    public function __construct(private array $personas) {}

    public function findByInfluencer(TenantId $tenantId, InfluencerId $influencerId): ?Persona
    {
        foreach ($this->personas as $persona) {
            if ($persona->tenantId->equals($tenantId) && $persona->influencerId->equals($influencerId)) {
                return $persona;
            }
        }

        return null;
    }

    public function save(Persona $persona): void {}
}
