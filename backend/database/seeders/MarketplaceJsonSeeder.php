<?php

namespace Database\Seeders;

use App\Services\MarketplaceSeederImporter;
use Illuminate\Database\Seeder;

class MarketplaceJsonSeeder extends Seeder
{
    public function run(): void
    {
        app(MarketplaceSeederImporter::class)->import(
            database_path('data/seeder.json'),
            fresh: false,
            batchSize: 1000,
        );
    }
}
