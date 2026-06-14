<?php

namespace App\Console\Commands;

use App\Services\MarketplaceSeederImporter;
use Illuminate\Console\Command;

class ImportMarketplaceSeeder extends Command
{
    protected $signature = 'marketplace:import-seeder
                            {--path= : Seeder JSON path; defaults to database/data/seeder.json}
                            {--fresh : Delete existing products and vendors before import}
                            {--batch=1000 : Number of records written per database batch}';

    protected $description = 'Stream vendors and products from the marketplace seeder JSON into the database';

    public function handle(MarketplaceSeederImporter $importer): int
    {
        $path = $this->option('path') ?: database_path('data/seeder.json');
        $lastReport = ['vendors' => 0, 'products' => 0];
        $result = $importer->import(
            $path,
            (bool) $this->option('fresh'),
            max(100, (int) $this->option('batch')),
            function (string $section, int $count) use (&$lastReport): void {
                if ($count - $lastReport[$section] >= 25000 || $section === 'vendors') {
                    $this->line(ucfirst($section).': '.number_format($count).' processed');
                    $lastReport[$section] = $count;
                }
            },
        );

        $this->components->info(number_format($result['vendors']).' vendors and '.number_format($result['products']).' products imported.');
        $this->components->warn('Product embeddings are not generated during bulk import. Run search:index separately when required.');

        return self::SUCCESS;
    }
}
