<?php

namespace App\Console\Commands;

use App\Services\ProductEmbeddingIndexer;
use App\Services\VectorMath;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use JsonException;
use RuntimeException;

class ImportProductEmbeddings extends Command
{
    protected $signature = 'search:import-embeddings
                            {--path= : Input JSONL path; defaults to storage/app/product-embeddings.jsonl}
                            {--only=all : Embedding type to import: all, text, or image}
                            {--batch=100 : Number of records processed per database batch}
                            {--require-existing : Fail when an exported seller_sku is missing in this database}';

    protected $description = 'Import precomputed product embeddings from JSONL into the database';

    private array $expectedDimensions = [];

    public function handle(): int
    {
        $mode = $this->mode();
        $batchSize = max(1, (int) $this->option('batch'));
        $path = $this->option('path') ?: storage_path('app/product-embeddings.jsonl');

        throw_unless(is_file($path), RuntimeException::class, "Embedding export not found: $path");
        $handle = fopen($path, 'rb');
        throw_unless($handle, RuntimeException::class, "Unable to open $path for reading.");

        $imported = 0;
        $skipped = 0;
        $batch = [];

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            try {
                $record = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new RuntimeException('Invalid embedding JSONL record near: '.substr($line, 0, 120), previous: $exception);
            }

            if (($record['type'] ?? null) !== 'product') {
                continue;
            }

            $batch[] = $record;
            if (count($batch) >= $batchSize) {
                [$done, $missing] = $this->importBatch($batch, $mode);
                $imported += $done;
                $skipped += $missing;
                $this->line(number_format($imported).' imported, '.number_format($skipped).' skipped');
                $batch = [];
            }
        }

        fclose($handle);

        if ($batch) {
            [$done, $missing] = $this->importBatch($batch, $mode);
            $imported += $done;
            $skipped += $missing;
        }

        $this->components->info(number_format($imported).' product embedding records imported.');
        if ($skipped > 0) {
            $this->components->warn(number_format($skipped).' records were skipped because their seller_sku was not found.');
        }

        return $skipped > 0 && $this->option('require-existing') ? self::FAILURE : self::SUCCESS;
    }

    private function mode(): string
    {
        $mode = (string) $this->option('only');
        if (! in_array($mode, [ProductEmbeddingIndexer::MODE_ALL, ProductEmbeddingIndexer::MODE_TEXT, ProductEmbeddingIndexer::MODE_IMAGE], true)) {
            throw new RuntimeException('--only must be all, text, or image.');
        }

        return $mode;
    }

    private function importBatch(array $records, string $mode): array
    {
        $skus = array_values(array_unique(array_column($records, 'seller_sku')));
        $products = DB::table('products')
            ->whereIn('seller_sku', $skus)
            ->select([
                'id',
                'seller_sku',
                DB::raw('text_embedding IS NOT NULL as has_text_embedding'),
                DB::raw('image_embedding IS NOT NULL as has_image_embedding'),
            ])
            ->get()
            ->keyBy('seller_sku');

        $imported = 0;
        $skipped = 0;

        foreach ($records as $record) {
            $product = $products[$record['seller_sku']] ?? null;
            if (! $product) {
                $skipped++;
                if ($this->option('require-existing')) {
                    throw new RuntimeException("Product not found for seller_sku: {$record['seller_sku']}");
                }

                continue;
            }

            $updates = ['updated_at' => now()];
            $hasText = (bool) $product->has_text_embedding;
            $hasImage = (bool) $product->has_image_embedding;

            if ($mode !== ProductEmbeddingIndexer::MODE_IMAGE && isset($record['text_embedding'])) {
                $this->assertDimensions('text_embedding_vector', $record['text_embedding']);
                $updates['text_embedding'] = json_encode($record['text_embedding'], JSON_THROW_ON_ERROR);
                $updates['text_embedding_model'] = $record['text_embedding_model'] ?? null;
                $hasText = true;
            }

            if ($mode !== ProductEmbeddingIndexer::MODE_TEXT && isset($record['image_embedding'])) {
                $this->assertDimensions('image_embedding_vector', $record['image_embedding']);
                $updates['image_embedding'] = json_encode($record['image_embedding'], JSON_THROW_ON_ERROR);
                $updates['image_embedding_model'] = $record['image_embedding_model'] ?? null;
                $hasImage = true;
            }

            if ($hasText && $hasImage) {
                $updates['embeddings_indexed_at'] = now();
            }

            if (DB::getDriverName() === 'pgsql') {
                if (array_key_exists('text_embedding', $updates)) {
                    $updates['text_embedding_vector'] = DB::raw("'".VectorMath::pgVector($record['text_embedding'])."'::vector");
                }
                if (array_key_exists('image_embedding', $updates)) {
                    $updates['image_embedding_vector'] = DB::raw("'".VectorMath::pgVector($record['image_embedding'])."'::vector");
                }
            }

            DB::table('products')->where('id', $product->id)->update($updates);
            $imported++;
        }

        return [$imported, $skipped];
    }

    private function assertDimensions(string $column, array $embedding): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $expected = $this->expectedDimensions[$column] ??= $this->columnDimensions($column);
        if ($expected !== null && $expected !== count($embedding)) {
            throw new RuntimeException(
                "$column expects $expected dimensions, but the import has ".count($embedding).' dimensions. '.
                'Regenerate embeddings with matching SEARCH_*_DIMENSIONS or migrate the vector column first.'
            );
        }
    }

    private function columnDimensions(string $column): ?int
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
            return null;
        }

        return (int) $matches[1];
    }
}
