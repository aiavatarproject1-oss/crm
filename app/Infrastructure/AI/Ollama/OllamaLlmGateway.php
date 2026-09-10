<?php

namespace App\Infrastructure\AI\Ollama;

use App\Application\AI\Contracts\LlmGatewayInterface;
use App\Application\AI\DTO\LlmRequest;
use App\Application\AI\DTO\LlmResponse;
use Illuminate\Http\Client\Factory;

final readonly class OllamaLlmGateway implements LlmGatewayInterface
{
    public function __construct(private Factory $http, private string $baseUrl, private string $model) {}

    public function generate(LlmRequest $request): LlmResponse
    {
        $startedAt = hrtime(true);
        $messages = $request->system_prompt === '' ? $request->messages : [['role' => 'system', 'content' => $request->system_prompt], ...$request->messages];
        $response = $this->http->baseUrl($this->baseUrl)->acceptJson()->post('/v1/chat/completions', [
            'model' => $this->model,
            'messages' => $messages,
            'stream' => false,
        ])->throw()->json();

        return new LlmResponse(
            (string) data_get($response, 'choices.0.message.content', ''),
            (string) ($response['model'] ?? $this->model),
            (int) data_get($response, 'usage.total_tokens', 0),
            (int) round((hrtime(true) - $startedAt) / 1_000_000),
            ['finish_reason' => data_get($response, 'choices.0.finish_reason'), 'provider' => 'ollama'],
        );
    }
}
