<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ProductEmbeddingIndexer;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

class IndexProductEmbeddings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'search:index
                            {--force : Re-index products that already have embeddings}
                            {--only=all : Embedding type to index: all, text, or image}
                            {--limit= : Maximum number of products to index in this run}
                            {--chunk=250 : Number of products streamed from the database at a time}
                            {--start-after-id=0 : Resume after this product ID}
                            {--sleep-ms=0 : Pause after each product to reduce provider/server pressure}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate text and image embeddings for the product catalogue';

    /**
     * Execute the console command.
     */
    public function handle(ProductEmbeddingIndexer $indexer): int
    {
        $mode = $this->mode();
        $limit = $this->option('limit') === null ? null : max(1, (int) $this->option('limit'));
        $chunk = max(1, (int) $this->option('chunk'));
        $startAfterId = max(0, (int) $this->option('start-after-id'));
        $sleepMs = max(0, (int) $this->option('sleep-ms'));

        $query = Product::query()
            ->with('vendor')
            ->where('id', '>', $startAfterId)
            ->orderBy('id');

        if (! $this->option('force')) {
            $this->scopeUnindexed($query, $mode);
        }

        $count = $query->toBase()->getCountForPagination();
        if ($limit !== null) {
            $count = min($count, $limit);
        }

        if ($count === 0) {
            $this->components->info("No products need $mode indexing.");

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($count);
        $failures = 0;
        $processed = 0;
        $lastId = $startAfterId;

        foreach ($query->lazyById($chunk) as $product) {
            if ($limit !== null && $processed >= $limit) {
                break;
            }

            try {
                $indexer->index($product, $mode);
            } catch (Throwable $exception) {
                $failures++;
                $this->newLine();
                $this->components->error("#{$product->id} {$product->name}: {$exception->getMessage()}");
            }

            $lastId = $product->id;
            $processed++;
            $bar->advance();

            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        }

        $bar->finish();
        $this->newLine(2);
        $this->components->info(($processed - $failures)." of $processed products indexed.");
        $this->line("Last processed product ID: $lastId");

        return $failures ? self::FAILURE : self::SUCCESS;
    }

    private function mode(): string
    {
        $mode = (string) $this->option('only');
        if (! in_array($mode, [ProductEmbeddingIndexer::MODE_ALL, ProductEmbeddingIndexer::MODE_TEXT, ProductEmbeddingIndexer::MODE_IMAGE], true)) {
            throw new InvalidArgumentException('--only must be all, text, or image.');
        }

        return $mode;
    }

    private function scopeUnindexed($query, string $mode): void
    {
        match ($mode) {
            ProductEmbeddingIndexer::MODE_TEXT => $query->whereNull('text_embedding'),
            ProductEmbeddingIndexer::MODE_IMAGE => $query->whereNull('image_embedding'),
            default => $query->where(function ($query): void {
                $query->whereNull('text_embedding')
                    ->orWhereNull('image_embedding')
                    ->orWhereNull('embeddings_indexed_at');
            }),
        };
    }
}
