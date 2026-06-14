<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

class ImageEmbeddingService
{
    public function embedUpload(UploadedFile $image): array
    {
        if (config('services.search.image_provider') === 'service') {
            return $this->client()
                ->attach('image', file_get_contents($image->getRealPath()), $image->getClientOriginalName())
                ->post(rtrim(config('services.search.image_service_url'), '/').'/embed')
                ->throw()->json('embedding');
        }

        return $this->localEmbedding($image->getClientOriginalName().hash_file('sha256', $image->getRealPath()));
    }

    public function embedUrl(string $url): array
    {
        if (config('services.search.image_provider') === 'service') {
            return $this->client()
                ->post(rtrim(config('services.search.image_service_url'), '/').'/embed-url', ['url' => $url])
                ->throw()->json('embedding');
        }

        return $this->localEmbedding($url);
    }

    public function model(): string
    {
        return config('services.search.image_provider') === 'service' ? 'image-service' : 'local-image-hash-v1';
    }

    private function localEmbedding(string $value): array
    {
        $bytes = array_values(unpack('C*', hash('sha512', $value, true)));

        return VectorMath::normalize(array_map(fn (int $byte) => ($byte / 127.5) - 1, $bytes));
    }

    private function client()
    {
        $client = Http::timeout(60);
        $token = config('services.search.image_service_token');

        return $token ? $client->withToken($token) : $client;
    }
}
