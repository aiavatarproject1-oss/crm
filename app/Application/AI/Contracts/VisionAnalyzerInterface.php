<?php

namespace App\Application\AI\Contracts;

interface VisionAnalyzerInterface
{
    /**
     * Analyze user-provided images. Must not call any model when $imageUrls is empty.
     *
     * @param  list<string>  $imageUrls
     * @return string|null  Natural-language analysis, or null when skipped / unavailable
     */
    public function analyze(array $imageUrls, string $userText = ''): ?string;
}
