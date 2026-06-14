<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

class ProductEmbeddingIndexer
{
    public function __construct(
        private readonly TextEmbeddingService $textEmbeddings,
        private readonly ImageEmbeddingService $imageEmbeddings,
    ) {}

    public function index(Product $product): void
    {
        $text = $this->textEmbeddings->embed($this->document($product));
        $image = $this->imageEmbeddings->embedUrl($product->image_url);

        $product->update([
            'text_embedding' => $text,
            'image_embedding' => $image,
            'text_embedding_model' => $this->textEmbeddings->model(),
            'image_embedding_model' => $this->imageEmbeddings->model(),
            'embeddings_indexed_at' => now(),
        ]);

        if (DB::getDriverName() === 'pgsql') {
            DB::table('products')->where('id', $product->id)->update([
                'text_embedding_vector' => DB::raw("'".VectorMath::pgVector($text)."'::vector"),
                'image_embedding_vector' => DB::raw("'".VectorMath::pgVector($image)."'::vector"),
            ]);
        }
    }

    public function document(Product $product): string
    {
        return implode('. ', [
            $product->vendor?->name,
            $product->vendor?->location,
            $product->name,
            $product->category,
            $product->description,
            implode(', ', $product->search_terms ?? []),
        ]);
    }
}
