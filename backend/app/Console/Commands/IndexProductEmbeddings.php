<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ProductEmbeddingIndexer;
use Illuminate\Console\Command;
use Throwable;

class IndexProductEmbeddings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'search:index {--force : Re-index products that already have embeddings}';

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
        $query = Product::query()->orderBy('id');
        if (! $this->option('force')) {
            $query->whereNull('embeddings_indexed_at');
        }

        $count = $query->count();
        if ($count === 0) {
            $this->components->info('All products are already indexed.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($count);
        $failures = 0;
        $query->each(function (Product $product) use ($indexer, $bar, &$failures): void {
            try {
                $indexer->index($product);
            } catch (Throwable $exception) {
                $failures++;
                $this->newLine();
                $this->components->error("{$product->name}: {$exception->getMessage()}");
            }
            $bar->advance();
        });
        $bar->finish();
        $this->newLine(2);
        $this->components->info(($count - $failures)." of $count products indexed.");

        return $failures ? self::FAILURE : self::SUCCESS;
    }
}
