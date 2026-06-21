<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ProductEmbeddingIndexer;
use Illuminate\Console\Command;
use RuntimeException;

class ExportProductEmbeddings extends Command
{
    protected $signature = 'search:export-embeddings
                            {--path= : Output JSONL path; defaults to storage/app/product-embeddings.jsonl}
                            {--only=all : Embedding type to export: all, text, or image}
                            {--chunk=500 : Number of products streamed from the database at a time}';

    protected $description = 'Export generated product embeddings as JSONL for production import';

    public function handle(): int
    {
        $mode = $this->mode();
        $chunk = max(1, (int) $this->option('chunk'));
        $path = $this->option('path') ?: storage_path('app/product-embeddings.jsonl');

        if (! is_dir(dirname($path)) && ! mkdir(dirname($path), 0777, true) && ! is_dir(dirname($path))) {
            throw new RuntimeException('Unable to create output directory: '.dirname($path));
        }

        $handle = fopen($path, 'wb');
        throw_unless($handle, RuntimeException::class, "Unable to open $path for writing.");

        $query = Product::query()->orderBy('id');
        $this->scopeIndexed($query, $mode);
        $count = $query->toBase()->getCountForPagination();

        fwrite($handle, json_encode([
            'type' => 'metadata',
            'version' => 1,
            'mode' => $mode,
            'products' => $count,
            'generated_at' => now()->toISOString(),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)."\n");

        $bar = $this->output->createProgressBar($count);
        $written = 0;

        foreach ($query->lazyById($chunk) as $product) {
            fwrite($handle, json_encode($this->payload($product, $mode), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)."\n");
            $written++;
            $bar->advance();
        }

        fclose($handle);
        $bar->finish();
        $this->newLine(2);
        $this->components->info(number_format($written)." product embedding records exported to $path.");

        return self::SUCCESS;
    }

    private function mode(): string
    {
        $mode = (string) $this->option('only');
        if (! in_array($mode, [ProductEmbeddingIndexer::MODE_ALL, ProductEmbeddingIndexer::MODE_TEXT, ProductEmbeddingIndexer::MODE_IMAGE], true)) {
            throw new RuntimeException('--only must be all, text, or image.');
        }

        return $mode;
    }

    private function scopeIndexed($query, string $mode): void
    {
        match ($mode) {
            ProductEmbeddingIndexer::MODE_TEXT => $query->whereNotNull('text_embedding'),
            ProductEmbeddingIndexer::MODE_IMAGE => $query->whereNotNull('image_embedding'),
            default => $query->whereNotNull('text_embedding')->whereNotNull('image_embedding'),
        };
    }

    private function payload(Product $product, string $mode): array
    {
        $payload = [
            'type' => 'product',
            'seller_sku' => $product->seller_sku,
            'name' => $product->name,
        ];

        if ($mode !== ProductEmbeddingIndexer::MODE_IMAGE) {
            $payload['text_embedding'] = $product->text_embedding;
            $payload['text_embedding_model'] = $product->text_embedding_model;
        }

        if ($mode !== ProductEmbeddingIndexer::MODE_TEXT) {
            $payload['image_embedding'] = $product->image_embedding;
            $payload['image_embedding_model'] = $product->image_embedding_model;
        }

        return $payload;
    }
}
