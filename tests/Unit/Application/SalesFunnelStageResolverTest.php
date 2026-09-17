<?php

namespace Tests\Unit\Application;

use App\Application\Context\Services\SalesFunnelStageResolver;
use PHPUnit\Framework\TestCase;

final class SalesFunnelStageResolverTest extends TestCase
{
    public function test_warmup_for_early_messages(): void
    {
        $resolver = new SalesFunnelStageResolver;
        $stage = $resolver->resolve([
            ['role' => 'user', 'content' => 'Hi'],
            ['role' => 'assistant', 'content' => 'hey'],
            ['role' => 'user', 'content' => 'you are so sexy'],
        ], 'you are so sexy');

        self::assertSame(SalesFunnelStageResolver::WARMUP, $stage);
    }

    public function test_buy_intent_jumps_to_sell(): void
    {
        $resolver = new SalesFunnelStageResolver;
        $stage = $resolver->resolve([
            ['role' => 'user', 'content' => 'Hi'],
        ], 'send me private pics');

        self::assertSame(SalesFunnelStageResolver::SELL, $stage);
    }

    public function test_tease_after_warmup_threshold(): void
    {
        $resolver = new SalesFunnelStageResolver;
        $messages = [];
        for ($i = 0; $i < 5; $i++) {
            $messages[] = ['role' => 'user', 'content' => 'msg '.$i];
            $messages[] = ['role' => 'assistant', 'content' => 'ok'];
        }

        $stage = $resolver->resolve($messages, 'how old are you?');

        self::assertSame(SalesFunnelStageResolver::TEASE, $stage);
    }
}
