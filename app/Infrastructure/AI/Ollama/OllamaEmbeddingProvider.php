<?php

namespace App\Infrastructure\AI\Ollama;

use App\Application\AI\Contracts\EmbeddingProviderInterface;
use App\Application\AI\DTO\EmbeddingResult;
use Illuminate\Http\Client\Factory;

final readonly class OllamaEmbeddingProvider implements EmbeddingProviderInterface
{
    public function __construct(private Factory $http, private string $baseUrl, private string $model) {}

    public function embed(string $text): EmbeddingResult
    {
        $response = $this->http->baseUrl($this->baseUrl)->acceptJson()->post('/api/embed', [
            'model' => $this->model,
            'input' => $text,
        ])->throw()->json();
        $vector = array_map('floatval', (array) data_get($response, 'embeddings.0', []));

        return new EmbeddingResult($vector, (string) ($response['model'] ?? $this->model), count($vector), ['provider' => 'ollama']);
    }
}
