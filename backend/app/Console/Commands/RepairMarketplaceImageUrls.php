<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairMarketplaceImageUrls extends Command
{
    protected $signature = 'marketplace:repair-image-urls
                            {--chunk=500 : Number of rows inspected per database chunk}
                            {--dry-run : Report changes without writing them}';

    protected $description = 'Normalize seeded Unsplash image URLs and clear stale product image embeddings';

    private const PHOTO_REPLACEMENTS = [
        '1583391733956-6c78276477e2' => '1551488831-00ddcb6c6bd3',
        '1581147036324-c1c89c2c8b5c' => '1581783898377-1c85bf937427',
    ];

    public function handle(): int
    {
        $chunk = max(1, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');

        $products = $this->repairTable(
            table: 'products',
            column: 'image_url',
            chunk: $chunk,
            dryRun: $dryRun,
            resetEmbeddings: true,
        );

        $vendors = $this->repairTable(
            table: 'vendors',
            column: 'banner_url',
            chunk: $chunk,
            dryRun: $dryRun,
            resetEmbeddings: false,
        );

        $mode = $dryRun ? 'would be updated' : 'updated';
        $this->components->info(number_format($products)." product image URLs $mode.");
        $this->components->info(number_format($vendors)." vendor banner URLs $mode.");

        if ($products > 0 && ! $dryRun) {
            $this->components->warn('Product image embeddings were cleared for changed URLs. Run search:index --only=image to regenerate them.');
        }

        return self::SUCCESS;
    }

    private function repairTable(string $table, string $column, int $chunk, bool $dryRun, bool $resetEmbeddings): int
    {
        $changed = 0;

        DB::table($table)->select('id', $column)->orderBy('id')->chunkById($chunk, function ($rows) use ($table, $column, $dryRun, $resetEmbeddings, &$changed): void {
            foreach ($rows as $row) {
                $current = $row->{$column};
                $fixed = is_string($current) ? $this->normalizeUnsplashUrl($current) : null;

                if ($fixed === null || $fixed === $current) {
                    continue;
                }

                $changed++;
                if ($dryRun) {
                    continue;
                }

                $updates = [
                    $column => $fixed,
                    'updated_at' => now(),
                ];

                if ($resetEmbeddings) {
                    $updates['image_embedding'] = null;
                    $updates['image_embedding_model'] = null;
                    $updates['embeddings_indexed_at'] = null;
                    if (DB::getDriverName() === 'pgsql') {
                        $updates['image_embedding_vector'] = null;
                    }
                }

                DB::table($table)->where('id', $row->id)->update($updates);
            }
        });

        return $changed;
    }

    private function normalizeUnsplashUrl(string $url): ?string
    {
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '';

        if ($host !== 'images.unsplash.com' || ! preg_match('#^/photo-(.+)$#', $path, $matches)) {
            return null;
        }

        $photoId = self::PHOTO_REPLACEMENTS[$matches[1]] ?? $matches[1];

        $query = [];
        parse_str($parts['query'] ?? '', $query);
        $query = [
            'ixlib' => 'rb-4.0.3',
            'auto' => $query['auto'] ?? 'format',
            'fit' => $query['fit'] ?? 'crop',
            'w' => $query['w'] ?? '900',
            'q' => $query['q'] ?? '80',
            ...array_diff_key($query, array_flip(['ixlib', 'auto', 'fit', 'w', 'q'])),
        ];

        return 'https://images.unsplash.com/photo-'.$photoId.'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }
}
