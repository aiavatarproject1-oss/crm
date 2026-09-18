<?php

namespace Tests\Unit\Application;

use App\Application\AI\Services\VisionGroundednessGuard;
use PHPUnit\Framework\TestCase;

final class VisionGroundednessGuardTest extends TestCase
{
    public function test_rejects_stale_jacket_when_vision_is_a_car(): void
    {
        $guard = new VisionGroundednessGuard;
        $vision = 'The image shows a luxurious black SUV, specifically a Cadillac Escalade, displayed in a showroom setting with LARTE Design branding.';
        $stale = "You're wearing a glossy red leather jacket with a slim-fit black turtleneck. The shaved head and trimmed beard add that sharp edge. Want me to send you the soft set? 😉";

        self::assertFalse($guard->isGrounded($stale, $vision));
        self::assertTrue($guard->isGrounded(
            'Sleek black Cadillac Escalade in a LARTE Design showroom — those multi-spoke wheels are insane.',
            $vision,
        ));
    }

    public function test_isolates_vision_turn_messages(): void
    {
        $guard = new VisionGroundednessGuard;
        $messages = $guard->messagesForVisionTurn(
            [
                ['role' => 'assistant', 'content' => 'You are wearing a red leather jacket'],
                ['role' => 'user', 'content' => 'hi'],
            ],
            'describe that with details',
            'Black Cadillac Escalade in a showroom.',
        );

        self::assertCount(1, $messages);
        self::assertSame('user', $messages[0]['role']);
        self::assertStringContainsString('Cadillac', $messages[0]['content']);
        self::assertStringNotContainsString('leather jacket', $messages[0]['content']);
    }

    public function test_forced_reply_includes_vision_facts(): void
    {
        $guard = new VisionGroundednessGuard;
        $reply = $guard->forcedReplyFromVision(
            'Close-up of an erect penis with dark pubic hair.',
            'describe that with details absolutely full details.',
        );

        self::assertStringContainsString('penis', mb_strtolower($reply));
        self::assertStringNotContainsString('leather jacket', mb_strtolower($reply));
    }
}
