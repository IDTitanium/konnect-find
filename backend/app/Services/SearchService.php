<?php

namespace App\Services;

use App\Models\Product;
use App\Models\SearchLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SearchService
{
    public function __construct(
        private readonly TextEmbeddingService $textEmbeddings,
        private readonly ImageEmbeddingService $imageEmbeddings,
        private readonly ProductEmbeddingIndexer $indexer,
    ) {}

    private const STOP_WORDS = [
        'and', 'are', 'but', 'for', 'from', 'has', 'have', 'into', 'its', 'not',
        'that', 'the', 'their', 'this', 'too', 'want', 'with', 'you', 'your',
    ];

    private const SYNONYMS = [
        'owambe' => ['party', 'occasion', 'traditional', 'celebration', 'ankara'],
        'burial' => ['black', 'formal', 'occasion', 'traditional'],
        'pikin' => ['child', 'children', 'kids', 'school'],
        'power' => ['generator', 'inverter', 'electricity', 'solar'],
        'heavy-duty' => ['durable', 'powerful', 'industrial'],
        'kicks' => ['sneakers', 'shoes', 'trainers'],
        'phone' => ['smartphone', 'mobile'],
        'work' => ['office', 'professional', 'formal'],
        'classy' => ['elegant', 'premium', 'formal'],
    ];

    public function search(?string $query, ?UploadedFile $image, string $sessionId, ?int $vendorId = null): array
    {
        $type = $query && $image ? 'combined' : ($image ? 'image' : 'text');
        $textResults = $query ? $this->rankText($query, $vendorId) : collect();
        $imageResults = $image ? $this->rankImage($image, $vendorId) : collect();
        $results = match ($type) {
            'combined' => $this->fuse($textResults, $imageResults),
            'image' => $imageResults,
            default => $textResults,
        };

        $log = SearchLog::create([
            'session_id' => $sessionId,
            'query_text' => $query,
            'image_name' => $image?->getClientOriginalName(),
            'search_type' => $type,
            'result_count' => $results->count(),
            'vendor_id' => $vendorId,
        ]);

        $results->each(fn (array $result, int $index) => $log->results()->create([
            'product_id' => $result['product']->id,
            'rank' => $index + 1,
            'score' => $result['score'],
            'source' => $result['source'],
        ]));

        return [
            'search_id' => $log->id,
            'search_type' => $type,
            'result_count' => $results->count(),
            'results' => $results->map(fn (array $result, int $index) => [
                ...$result['product']->toArray(),
                'price' => $result['product']->price_kobo / 100,
                'rank' => $index + 1,
                'score' => round($result['score'], 4),
                'source' => $result['source'],
            ])->values(),
        ];
    }

    public function retrieveText(string $query, int $limit = 12): Collection
    {
        return $this->rankText($query)->take($limit)->values();
    }

    private function rankText(string $query, ?int $vendorId = null): Collection
    {
        $queryTokens = $this->tokens($query);
        $expanded = $queryTokens->flatMap(fn (string $token) => [$token, ...(self::SYNONYMS[$token] ?? [])])->unique();
        $queryEmbedding = $this->textEmbeddings->embed(implode(' ', $expanded->all()));

        return $this->vectorCandidates('text_embedding_vector', $queryEmbedding, $vendorId)
            ->map(function (Product $product) use ($queryTokens, $expanded, $queryEmbedding): array {
                $document = $this->tokens(implode(' ', [
                    $this->indexer->document($product),
                ]));
                $exact = $queryTokens->intersect($document)->count();
                $semantic = $expanded->intersect($document)->count();
                $lexical = ($exact * 0.65 + $semantic * 0.35) / max(1, $expanded->count());
                $catalogueTerms = $this->tokens($product->name.' '.implode(' ', $product->search_terms ?? []));
                $merchandising = $queryTokens->intersect($catalogueTerms)->count() / max(1, $queryTokens->count());
                $vector = $product->text_embedding ? max(0, VectorMath::cosine($queryEmbedding, $product->text_embedding)) : 0;
                $score = $product->text_embedding
                    ? ($lexical * 0.5 + $merchandising * 0.2 + $vector * 0.3)
                    : ($lexical * 0.75 + $merchandising * 0.25);

                return ['product' => $product, 'score' => min(1, $score), 'source' => 'text'];
            })->filter(fn (array $result) => $result['score'] > 0.04)
            ->sortByDesc('score')->take(12)->values();
    }

    private function rankImage(UploadedFile $image, ?int $vendorId = null): Collection
    {
        $embedding = $this->imageEmbeddings->embedUpload($image);
        $tokens = $this->tokens(pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME));

        return $this->vectorCandidates('image_embedding_vector', $embedding, $vendorId)
            ->map(function (Product $product) use ($tokens, $image, $embedding): array {
                $document = $this->tokens($product->name.' '.$product->category.' '.implode(' ', $product->search_terms ?? []));
                $nameScore = $tokens->intersect($document)->count() / max(1, $tokens->count());
                $vector = $product->image_embedding ? max(0, VectorMath::cosine($embedding, $product->image_embedding)) : 0;
                $stableTieBreaker = (crc32($image->getClientOriginalName().$product->id) % 10) / 100;
                $score = $product->image_embedding ? ($vector * 0.85 + $nameScore * 0.15) : (0.55 + $nameScore * 0.3 + $stableTieBreaker);

                return ['product' => $product, 'score' => min(1, $score), 'source' => 'image'];
            })->sortByDesc('score')->take(12)->values();
    }

    private function fuse(Collection $textResults, Collection $imageResults): Collection
    {
        $scores = [];
        foreach ([$textResults, $imageResults] as $results) {
            foreach ($results as $index => $result) {
                $id = $result['product']->id;
                $scores[$id] ??= ['product' => $result['product'], 'score' => 0, 'source' => 'combined'];
                $scores[$id]['score'] += 1 / (61 + $index);
            }
        }

        return collect($scores)->sortByDesc('score')->take(12)->values();
    }

    private function tokens(string $value): Collection
    {
        return collect(preg_split('/[^a-z0-9-]+/', Str::lower($value), -1, PREG_SPLIT_NO_EMPTY))
            ->filter(fn (string $token) => strlen($token) > 2 && ! in_array($token, self::STOP_WORDS, true))
            ->unique()->values();
    }

    private function vectorCandidates(string $column, array $embedding, ?int $vendorId = null): Collection
    {
        $baseQuery = Product::query()
            ->with('vendor')
            ->where('products.is_active', true)
            ->whereHas('vendor', fn ($query) => $query->where('is_active', true))
            ->when($vendorId, fn ($query) => $query->where('vendor_id', $vendorId));

        if (DB::getDriverName() !== 'pgsql') {
            return $baseQuery->get();
        }

        $jsonColumn = $column === 'text_embedding_vector' ? 'text_embedding' : 'image_embedding';
        if (! (clone $baseQuery)->whereNotNull($jsonColumn)->exists()) {
            return $baseQuery->get();
        }

        return $baseQuery
            ->whereNotNull($jsonColumn)
            ->orderByRaw("$column <=> CAST(? AS vector)", [VectorMath::pgVector($embedding)])
            ->limit(100)
            ->get();
    }
}
