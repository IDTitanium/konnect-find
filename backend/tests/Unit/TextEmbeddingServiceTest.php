<?php

namespace Tests\Unit;

use App\Services\TextEmbeddingService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TextEmbeddingServiceTest extends TestCase
{
    public function test_openai_request_uses_the_configured_model_and_dimensions(): void
    {
        config()->set('services.search.text_provider', 'openai');
        config()->set('services.search.text_dimensions', 256);
        config()->set('services.search.openai_key', 'test-key');
        config()->set('services.search.openai_base_url', 'https://api.openai.test/v1');
        config()->set('services.search.openai_embedding_model', 'text-embedding-3-small');

        Http::fake([
            'https://api.openai.test/v1/embeddings' => Http::response([
                'data' => [
                    ['embedding' => array_fill(0, 256, 0.1)],
                ],
            ]),
        ]);

        $embedding = app(TextEmbeddingService::class)->embed('Nigerian marketplace product');

        $this->assertCount(256, $embedding);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.openai.test/v1/embeddings'
            && $request['model'] === 'text-embedding-3-small'
            && $request['dimensions'] === 256
            && $request->hasHeader('Authorization', 'Bearer test-key'));
    }
}
