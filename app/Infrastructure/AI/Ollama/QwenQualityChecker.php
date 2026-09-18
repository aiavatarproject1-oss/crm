<?php

namespace App\Infrastructure\AI\Ollama;

use App\Application\Context\DTO\ConversationContext;
use App\Application\Quality\Contracts\QualityCheckerInterface;
use App\Application\Quality\DTO\QualityResult;
use App\Application\Quality\Services\QualityClaimJudge;
use Illuminate\Http\Client\Factory;

/**
 * Quality gate: LLM extracts claims from the reply; PHP compares them to Character settings.
 */
final readonly class QwenQualityChecker implements QualityCheckerInterface
{
    public function __construct(
        private Factory $http,
        private string $baseUrl,
        private string $model,
        private QualityClaimJudge $judge = new QualityClaimJudge,
    ) {}

    public function evaluate(string $userMessage, string $aiResponse, ConversationContext $context): QualityResult
    {
        $character = $context->character;
        $extraction = $this->extractClaims($userMessage, $aiResponse, $context);

        if ($extraction === null) {
            return new QualityResult(
                false,
                0.0,
                ['invalid_checker_response'],
                'Quality checker returned invalid JSON.',
                ['provider' => 'ollama', 'model' => $this->model, 'quality_model' => $this->model],
            );
        }

        return $this->judge->judge($extraction, $character, $userMessage, [
            'provider' => 'ollama',
            'model' => $this->model,
            'quality_model' => $this->model,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractClaims(string $userMessage, string $aiResponse, ConversationContext $context): ?array
    {
        $character = $context->character;
        $system = implode("\n", [
            'Extract factual claims from the assistant DM reply. Do NOT decide approve/reject.',
            'Return ONLY JSON with these keys:',
            '- stated_age: integer or null (any age the assistant claims for herself)',
            '- stated_name: string or null (name she claims for herself)',
            '- stated_city: string or null',
            '- stated_country: string or null',
            '- used_emojis: array of emoji strings found in the reply',
            '- admits_being_ai: boolean',
            '- dumps_bio_or_backstory: boolean (true if she dumps bio/hobbies/backstory unprompted)',
            '- invents_sent_media: boolean',
            '- early_sales_pitch: boolean',
            '- checklist: object map of checklist item => boolean pass (only for items provided)',
            '- notes: short string',
        ]);

        $payload = [
            'user_message' => $userMessage,
            'ai_response' => $aiResponse,
            'checklist' => $character?->qualityChecklist() ?? [],
            'character_identity_for_reference_only' => $character === null ? null : [
                'name' => $character->identity['name'] ?? $character->displayName,
                'age' => $character->identity['age'] ?? null,
                'city' => $character->identity['city'] ?? null,
                'country' => $character->identity['country'] ?? null,
            ],
        ];

        $response = $this->http->baseUrl($this->baseUrl)->acceptJson()->timeout(120)->post('/v1/chat/completions', [
            'model' => $this->model,
            'stream' => false,
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
            ],
        ])->throw()->json();

        $content = (string) data_get($response, 'choices.0.message.content', '');
        $result = json_decode($content, true);

        return is_array($result) ? $result : null;
    }
}
