<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProductEmbeddingIndexer
{
    public const MODE_ALL = 'all';
    public const MODE_TEXT = 'text';
    public const MODE_IMAGE = 'image';

    public function __construct(
        private readonly TextEmbeddingService $textEmbeddings,
        private readonly ImageEmbeddingService $imageEmbeddings,
    ) {}

    public function index(Product $product, string $mode = self::MODE_ALL): void
    {
        match ($mode) {
            self::MODE_TEXT => $this->indexText($product),
            self::MODE_IMAGE => $this->indexImage($product),
            self::MODE_ALL => $this->indexAll($product),
            default => throw new RuntimeException("Unsupported indexing mode: $mode"),
        };
    }

    public function indexAll(Product $product): void
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
            $this->assertPgVectorDimensions('text_embedding_vector', $text);
            $this->assertPgVectorDimensions('image_embedding_vector', $image);

            DB::table('products')->where('id', $product->id)->update([
                'text_embedding_vector' => DB::raw("'".VectorMath::pgVector($text)."'::vector"),
                'image_embedding_vector' => DB::raw("'".VectorMath::pgVector($image)."'::vector"),
            ]);
        }
    }

    public function indexText(Product $product): void
    {
        $text = $this->textEmbeddings->embed($this->document($product));

        $updates = [
            'text_embedding' => $text,
            'text_embedding_model' => $this->textEmbeddings->model(),
            'embeddings_indexed_at' => $product->image_embedding ? now() : null,
        ];

        if ($product->image_embedding && ! $product->embeddings_indexed_at) {
            $updates['embeddings_indexed_at'] = now();
        }

        $product->update($updates);

        if (DB::getDriverName() === 'pgsql') {
            $this->assertPgVectorDimensions('text_embedding_vector', $text);

            DB::table('products')->where('id', $product->id)->update([
                'text_embedding_vector' => DB::raw("'".VectorMath::pgVector($text)."'::vector"),
            ]);
        }
    }

    public function indexImage(Product $product): void
    {
        $image = $this->imageEmbeddings->embedUrl($product->image_url);

        $updates = [
            'image_embedding' => $image,
            'image_embedding_model' => $this->imageEmbeddings->model(),
            'embeddings_indexed_at' => $product->text_embedding ? now() : null,
        ];

        if ($product->text_embedding && ! $product->embeddings_indexed_at) {
            $updates['embeddings_indexed_at'] = now();
        }

        $product->update($updates);

        if (DB::getDriverName() === 'pgsql') {
            $this->assertPgVectorDimensions('image_embedding_vector', $image);

            DB::table('products')->where('id', $product->id)->update([
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

    private function assertPgVectorDimensions(string $column, array $embedding): void
    {
        $row = DB::selectOne(<<<'SQL'
            SELECT format_type(a.atttypid, a.atttypmod) AS type
            FROM pg_attribute a
            WHERE a.attrelid = 'products'::regclass
              AND a.attname = ?
              AND NOT a.attisdropped
            SQL, [$column]);

        $type = $row->type ?? null;
        if (! is_string($type) || ! preg_match('/^vector\((\d+)\)$/', $type, $matches)) {
            return;
        }

        $expected = (int) $matches[1];
        $actual = count($embedding);

        if ($expected !== $actual) {
            throw new RuntimeException(
                "$column is $type, but the generated embedding has $actual dimensions. ".
                'Keep the matching SEARCH_*_DIMENSIONS value or migrate to a new vector dimension before re-indexing.'
            );
        }
    }
}
