<?php

namespace App\Application\Context\Services;

/**
 * Decide whether the influencer should only flirt, soft-tease, or sell.
 */
final readonly class SalesFunnelStageResolver
{
    public const WARMUP = 'warmup';

    public const TEASE = 'tease';

    public const SELL = 'sell';

    public const DEFAULT_WARMUP_MAX_USER_MESSAGES = 4;

    public const DEFAULT_TEASE_MAX_USER_MESSAGES = 8;

    /** @var list<string> */
    public const DEFAULT_BUY_INTENT_KEYWORDS = [
        'pic', 'pics', 'photo', 'photos', 'nude', 'nudes', 'video', 'videos',
        'custom', 'customs', 'pay', 'price', 'buy', 'purchase', 'send me',
        'onlyfans', 'ppv', 'content', 'private',
    ];

    public function __construct(
        private int $warmupMaxUserMessages = self::DEFAULT_WARMUP_MAX_USER_MESSAGES,
        private int $teaseMaxUserMessages = self::DEFAULT_TEASE_MAX_USER_MESSAGES,
        /** @var list<string> */
        private array $buyIntentKeywords = self::DEFAULT_BUY_INTENT_KEYWORDS,
    ) {}

    /**
     * @param  list<array{role?: string, content?: string}>  $recentMessages
     */
    public function resolve(array $recentMessages, string $currentUserText = ''): string
    {
        if ($this->hasBuyIntent($currentUserText, $this->buyIntentKeywords)) {
            return self::SELL;
        }

        $userMessages = 0;
        foreach ($recentMessages as $message) {
            $role = strtolower((string) ($message['role'] ?? ''));
            if ($role === 'user') {
                $userMessages++;
            }
        }

        if ($userMessages <= max(1, $this->warmupMaxUserMessages)) {
            return self::WARMUP;
        }

        if ($userMessages <= max($this->warmupMaxUserMessages + 1, $this->teaseMaxUserMessages)) {
            return self::TEASE;
        }

        return self::SELL;
    }

    /**
     * @param  list<string>  $keywords
     */
    private function hasBuyIntent(string $text, array $keywords): bool
    {
        $normalized = strtolower(trim($text));
        if ($normalized === '') {
            return false;
        }

        foreach ($keywords as $keyword) {
            $keyword = strtolower(trim((string) $keyword));
            if ($keyword !== '' && str_contains($normalized, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
