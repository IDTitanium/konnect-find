<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class TextEmbeddingService
{
    public function embed(string $text): array
    {
        return config('services.search.text_provider') === 'openai'
            ? $this->openAiEmbedding($text)
            : $this->localEmbedding($text);
    }

    public function model(): string
    {
        return config('services.search.text_provider') === 'openai'
            ? config('services.search.openai_embedding_model')
            : 'local-feature-hash-v1';
    }

    private function openAiEmbedding(string $text): array
    {
        $key = config('services.search.openai_key');
        throw_unless($key, RuntimeException::class, 'OPENAI_API_KEY is required when SEARCH_TEXT_PROVIDER=openai.');

        $response = Http::withToken($key)
            ->timeout(30)
            ->post(rtrim(config('services.search.openai_base_url'), '/').'/embeddings', [
                'model' => config('services.search.openai_embedding_model'),
                'input' => $text,
            ])->throw()->json();

        return $response['data'][0]['embedding'];
    }

    private function localEmbedding(string $text): array
    {
        $dimensions = config('services.search.text_dimensions');
        $vector = array_fill(0, $dimensions, 0.0);
        $tokens = preg_split('/[^a-z0-9-]+/', Str::lower($text), -1, PREG_SPLIT_NO_EMPTY);

        foreach ($tokens as $token) {
            $hash = crc32($token);
            $index = $hash % $dimensions;
            $vector[$index] += ($hash & 1) ? 1 : -1;
        }

        return VectorMath::normalize($vector);
    }
}
