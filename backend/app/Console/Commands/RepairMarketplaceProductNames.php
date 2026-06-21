<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\MarketplaceProductNamer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairMarketplaceProductNames extends Command
{
    protected $signature = 'marketplace:repair-product-names
                            {--seed=3571 : Seed used by marketplace:generate-seeder}
                            {--chunk=500 : Number of products inspected per database chunk}
                            {--dry-run : Report changes without writing them}';

    protected $description = 'Replace generic generated product names with category-aware marketplace names';

    private const CATEGORY_CONTEXT = [
        'Women Fashion' => ['Ankara Midi Dress', 'fashion'],
        'Men Fashion' => ['Premium Senator Kaftan', 'fashion'],
        'Traditional Fabrics' => ['Handwoven Aso Oke Bundle', 'fashion'],
        'Footwear' => ['Everyday Leather Loafers', 'footwear'],
        'Sneakers' => ['Street Runner Sneakers', 'footwear'],
        'Bags' => ['Durable School Backpack', 'bags'],
        'Handbags' => ['Structured Occasion Handbag', 'bags'],
        'Phones' => ['Dual SIM Android Smartphone', 'electronics'],
        'Laptops' => ['Professional Work Laptop', 'electronics'],
        'Audio' => ['Noise Cancelling Headphones', 'electronics'],
        'Power' => ['Smart Home Inverter', 'power'],
        'Solar' => ['Portable Solar Generator', 'power'],
        'Generators' => ['Fuel Efficient Generator', 'power'],
        'Kitchen' => ['Non-Stick Cookware Set', 'home'],
        'Dining' => ['Ceramic Dinner Set', 'home'],
        'Furniture' => ['Modern Living Room Sofa', 'home'],
        'Home Decor' => ['Handcrafted Woven Basket', 'home'],
        'Beauty' => ['Shea Butter Skincare Set', 'beauty'],
        'Hair Care' => ['Natural Hair Care Bundle', 'beauty'],
        'Fragrances' => ['Long Lasting Eau de Parfum', 'beauty'],
        'Baby Products' => ['Newborn Baby Essentials Set', 'family'],
        'Groceries' => ['Premium Nigerian Rice Bag', 'food'],
        'Food Staples' => ['Beans and Garri Family Pack', 'food'],
        'Spices' => ['Nigerian Cooking Spice Box', 'food'],
        'Drinks' => ['Hibiscus Zobo Drink Pack', 'food'],
        'Fitness' => ['Home Workout Equipment Set', 'sports'],
        'Football' => ['Professional Football Kit', 'sports'],
        'Auto Parts' => ['Vehicle Maintenance Kit', 'automotive'],
        'Motorcycles' => ['Motorcycle Rider Safety Kit', 'automotive'],
        'Books' => ['Nigerian Contemporary Fiction Set', 'books'],
        'Stationery' => ['School Stationery Bundle', 'books'],
        'Agriculture' => ['Small Farm Starter Tools', 'agriculture'],
        'Tools' => ['Professional Hand Tool Box', 'tools'],
        'Office' => ['Ergonomic Office Chair', 'office'],
        'Watches' => ['Classic Everyday Wristwatch', 'accessories'],
        'Jewellery' => ['Gold Plated Jewellery Set', 'accessories'],
        'Gaming' => ['Wireless Gaming Controller', 'electronics'],
        'Appliances' => ['Energy Saving Standing Fan', 'home'],
        'Cleaning' => ['Household Cleaning Bundle', 'home'],
        'Pet Supplies' => ['Pet Care Essentials Pack', 'family'],
    ];

    public function handle(MarketplaceProductNamer $namer): int
    {
        $seed = (int) $this->option('seed');
        $chunk = max(1, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');
        $changed = 0;
        $skipped = 0;

        Product::query()
            ->with('vendor')
            ->where('seller_sku', 'like', 'V%-P%')
            ->orderBy('id')
            ->chunkById($chunk, function ($products) use ($namer, $seed, $dryRun, &$changed, &$skipped): void {
                foreach ($products as $product) {
                    $coordinates = $this->coordinates($product->seller_sku);
                    $context = self::CATEGORY_CONTEXT[$product->category] ?? null;

                    if (! $coordinates || ! $context) {
                        $skipped++;
                        continue;
                    }

                    [$vendorNumber, $productNumber] = $coordinates;
                    [$baseName, $department] = $context;
                    $location = $product->vendor?->location ?? 'Nigeria';
                    $name = $namer->name($product->category, $baseName, $department, $vendorNumber, $productNumber, $seed);
                    $description = $namer->description($name, $product->category, $department, $location);

                    if ($product->name === $name && $product->description === $description) {
                        continue;
                    }

                    $changed++;
                    if ($dryRun) {
                        continue;
                    }

                    $updates = [
                        'name' => $name,
                        'description' => $description,
                        'text_embedding' => null,
                        'text_embedding_model' => null,
                        'embeddings_indexed_at' => null,
                        'updated_at' => now(),
                    ];

                    if (DB::getDriverName() === 'pgsql') {
                        $updates['text_embedding_vector'] = null;
                    }

                    DB::table('products')->where('id', $product->id)->update($updates);
                }
            });

        $mode = $dryRun ? 'would be renamed' : 'renamed';
        $this->components->info(number_format($changed)." products $mode.");
        if ($skipped > 0) {
            $this->components->warn(number_format($skipped).' products were skipped because their SKU or category was not generated by the large marketplace seeder.');
        }
        if ($changed > 0 && ! $dryRun) {
            $this->components->warn('Text embeddings were cleared for renamed products. Run search:index --only=text to regenerate them.');
        }

        return self::SUCCESS;
    }

    private function coordinates(?string $sellerSku): ?array
    {
        if (! is_string($sellerSku) || ! preg_match('/^V(\d+)-P(\d+)$/', $sellerSku, $matches)) {
            return null;
        }

        return [(int) $matches[1], (int) $matches[2]];
    }
}
