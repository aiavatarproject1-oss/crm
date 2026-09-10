<?php

namespace App\Infrastructure\AI\Ollama;

use App\Application\Context\DTO\ConversationContext;
use App\Application\Quality\Contracts\QualityCheckerInterface;
use App\Application\Quality\DTO\QualityResult;
use Illuminate\Http\Client\Factory;

final readonly class QwenQualityChecker implements QualityCheckerInterface
{
    public function __construct(private Factory $http, private string $baseUrl, private string $model) {}

    public function evaluate(string $userMessage, string $aiResponse, ConversationContext $context): QualityResult
    {
        $response = $this->http->baseUrl($this->baseUrl)->acceptJson()->post('/v1/chat/completions', [
            'model' => $this->model,
            'stream' => false,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => 'Evaluate the assistant response for safety, relevance, persona consistency, and factual grounding. Return JSON with approved (boolean), score (0..1), issues (array), and reason (string).'],
                ['role' => 'user', 'content' => json_encode([
                    'user_message' => $userMessage,
                    'ai_response' => $aiResponse,
                    'context' => [
                        'history' => $context->recent_messages,
                        'memory' => $context->user_memories,
                        'persona' => $context->influencer_persona->toArray(),
                        'knowledge' => $context->knowledge_context,
                    ],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
            ],
        ])->throw()->json();

        $content = (string) data_get($response, 'choices.0.message.content', '');
        $result = json_decode($content, true);
        if (! is_array($result)) {
            return new QualityResult(false, 0.0, ['invalid_checker_response'], 'Quality checker returned invalid JSON.', ['provider' => 'ollama', 'model' => $this->model]);
        }

        return new QualityResult(
            (bool) ($result['approved'] ?? false),
            max(0.0, min(1.0, (float) ($result['score'] ?? 0.0))),
            array_values((array) ($result['issues'] ?? [])),
            (string) ($result['reason'] ?? ''),
            ['provider' => 'ollama', 'model' => (string) ($response['model'] ?? $this->model)],
        );
    }
}
