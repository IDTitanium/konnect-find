<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Vendor;
use App\Services\MarketplaceSeederImporter;
use Illuminate\Console\Command;
use RuntimeException;

class BootstrapLargeMarketplace extends Command
{
    protected $signature = 'marketplace:bootstrap-large
                            {--vendors=500 : Number of vendors}
                            {--products-per-vendor=500 : Products generated for each vendor}
                            {--batch=1000 : Number of records written per database batch}
                            {--seed=3571 : Deterministic generation seed}
                            {--replace : Delete existing products and vendors before import}
                            {--force : Allow destructive replacement without confirmation}';

    protected $description = 'Generate, import, verify, and clean up a large marketplace catalogue';

    public function handle(MarketplaceSeederImporter $importer): int
    {
        $vendors = max(1, (int) $this->option('vendors'));
        $productsPerVendor = max(1, (int) $this->option('products-per-vendor'));
        $batchSize = max(100, (int) $this->option('batch'));
        $expectedProducts = $vendors * $productsPerVendor;
        $replace = (bool) $this->option('replace');

        if ($replace && ! $this->option('force') && ! $this->confirm('Delete all existing products and vendors before importing?')) {
            $this->components->warn('Marketplace bootstrap cancelled.');

            return self::FAILURE;
        }

        $path = storage_path('app/marketplace-seeder-'.getmypid().'.json');

        try {
            $this->components->info('Generating the temporary marketplace catalogue...');
            $generateResult = $this->call('marketplace:generate-seeder', [
                '--vendors' => $vendors,
                '--products-per-vendor' => $productsPerVendor,
                '--path' => $path,
                '--seed' => (int) $this->option('seed'),
            ]);

            throw_unless($generateResult === self::SUCCESS, RuntimeException::class, 'Marketplace catalogue generation failed.');

            $this->components->info('Streaming the catalogue into the database...');
            $result = $importer->import(
                $path,
                fresh: $replace,
                batchSize: $batchSize,
                progress: function (string $section, int $count): void {
                    if ($section === 'products' && $count % 25000 === 0) {
                        $this->line(number_format($count).' products processed');
                    }
                },
            );

            throw_unless(
                $result['vendors'] === $vendors && $result['products'] === $expectedProducts,
                RuntimeException::class,
                'Imported record totals do not match the generated catalogue.',
            );

            if ($replace) {
                throw_unless(
                    Vendor::query()->count() === $vendors && Product::query()->count() === $expectedProducts,
                    RuntimeException::class,
                    'Database record totals do not match the expected replacement catalogue.',
                );
            }

            $this->components->info(number_format($vendors).' vendors and '.number_format($expectedProducts).' products are ready.');
            $this->components->warn('Embeddings were not generated. Index them separately using a background worker.');

            return self::SUCCESS;
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
