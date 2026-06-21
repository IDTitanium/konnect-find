<?php

namespace App\Console\Commands;

use App\Services\MarketplaceProductNamer;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use RuntimeException;

class GenerateMarketplaceSeeder extends Command
{
    protected $signature = 'marketplace:generate-seeder
                            {--vendors=500 : Number of vendors}
                            {--products-per-vendor=500 : Products generated for each vendor}
                            {--path= : Output path; defaults to database/data/seeder.json}
                            {--seed=3571 : Deterministic generation seed}';

    protected $description = 'Generate a large Nigerian-context marketplace seeder JSON file';

    private const LOCATIONS = [
        'Lagos', 'Abuja', 'Port Harcourt', 'Ibadan', 'Kano', 'Benin City', 'Enugu', 'Abeokuta',
        'Ilorin', 'Jos', 'Kaduna', 'Owerri', 'Uyo', 'Calabar', 'Akure', 'Warri', 'Asaba',
        'Aba', 'Onitsha', 'Maiduguri',
    ];

    private const VENDOR_PREFIXES = [
        'Aso', 'Naija', 'Savanna', 'Palm', 'Coral', 'Zobo', 'Kora', 'Arewa', 'Eko', 'Nile',
        'Ebony', 'Sunrise', 'Mainland', 'Heritage', 'Cedar', 'Gold', 'Urban', 'Market', 'Prime', 'Royal',
        'Green', 'Blue', 'Amber', 'Velvet', 'Nova',
    ];

    private const VENDOR_SUFFIXES = [
        'Collective', 'Market', 'Store', 'Hub', 'House', 'Essentials', 'Trading', 'Mart',
        'Gallery', 'Depot', 'Place', 'Works', 'Corner', 'Supply', 'Outfitters', 'Living',
        'Styles', 'Tech', 'Foods', 'Enterprises',
    ];

    private const CATEGORIES = [
        ['Women Fashion', 'Ankara Midi Dress', 'fashion', '1595777457583-95e059d581b8', ['ankara', 'owambe', 'dress', 'women']],
        ['Men Fashion', 'Premium Senator Kaftan', 'fashion', '1617137968427-85924c800a22', ['senator', 'kaftan', 'men', 'traditional']],
        ['Traditional Fabrics', 'Handwoven Aso Oke Bundle', 'fashion', '1551488831-00ddcb6c6bd3', ['aso oke', 'fabric', 'traditional', 'ceremony']],
        ['Footwear', 'Everyday Leather Loafers', 'footwear', '1533867617858-e7b97e060509', ['shoes', 'loafers', 'office', 'leather']],
        ['Sneakers', 'Street Runner Sneakers', 'footwear', '1542291026-7eec264c27ff', ['sneakers', 'kicks', 'trainers', 'durable']],
        ['Bags', 'Durable School Backpack', 'bags', '1553062407-98eeb64c6a62', ['bag', 'school', 'pikin', 'backpack']],
        ['Handbags', 'Structured Occasion Handbag', 'bags', '1584917865442-de89df76afd3', ['handbag', 'occasion', 'women', 'bag']],
        ['Phones', 'Dual SIM Android Smartphone', 'electronics', '1511707171634-5f897ff02aa9', ['phone', 'smartphone', 'android', 'mobile']],
        ['Laptops', 'Professional Work Laptop', 'electronics', '1496181133206-80ce9b88a853', ['laptop', 'computer', 'office', 'work']],
        ['Audio', 'Noise Cancelling Headphones', 'electronics', '1505740420928-5e560c06d30e', ['headphones', 'music', 'wireless', 'audio']],
        ['Power', 'Smart Home Inverter', 'power', '1620714223084-8fcacc6dfd8d', ['power', 'inverter', 'electricity', 'backup']],
        ['Solar', 'Portable Solar Generator', 'power', '1508514177221-188b1cf16e9d', ['solar', 'generator', 'power', 'renewable']],
        ['Generators', 'Fuel Efficient Generator', 'power', '1473341304170-971dccb5ac1e', ['generator', 'power', 'petrol', 'heavy-duty']],
        ['Kitchen', 'Non-Stick Cookware Set', 'home', '1556911220-bff31c812dba', ['kitchen', 'cookware', 'pots', 'home']],
        ['Dining', 'Ceramic Dinner Set', 'home', '1603199506016-b9a594b593c0', ['plates', 'dining', 'ceramic', 'home']],
        ['Furniture', 'Modern Living Room Sofa', 'home', '1555041469-a586c61ea9bc', ['sofa', 'furniture', 'living room', 'home']],
        ['Home Decor', 'Handcrafted Woven Basket', 'home', '1618220179428-22790b461013', ['decor', 'woven', 'basket', 'home']],
        ['Beauty', 'Shea Butter Skincare Set', 'beauty', '1598440947619-2c35fc9aa908', ['shea butter', 'skincare', 'beauty', 'natural']],
        ['Hair Care', 'Natural Hair Care Bundle', 'beauty', '1522337360788-8b13dee7a37e', ['hair', 'natural hair', 'beauty', 'care']],
        ['Fragrances', 'Long Lasting Eau de Parfum', 'beauty', '1541643600914-78b084683601', ['perfume', 'fragrance', 'beauty', 'gift']],
        ['Baby Products', 'Newborn Baby Essentials Set', 'family', '1519689680058-324335c77eba', ['baby', 'newborn', 'family', 'essentials']],
        ['Groceries', 'Premium Nigerian Rice Bag', 'food', '1586201375761-83865001e31c', ['rice', 'groceries', 'food', 'staple']],
        ['Food Staples', 'Beans and Garri Family Pack', 'food', '1606787366850-de6330128bfc', ['beans', 'garri', 'food', 'groceries']],
        ['Spices', 'Nigerian Cooking Spice Box', 'food', '1596040033229-a9821ebd058d', ['spices', 'cooking', 'food', 'kitchen']],
        ['Drinks', 'Hibiscus Zobo Drink Pack', 'food', '1544145945-f90425340c7e', ['zobo', 'drink', 'hibiscus', 'refreshment']],
        ['Fitness', 'Home Workout Equipment Set', 'sports', '1517836357463-d25dfeac3438', ['fitness', 'workout', 'gym', 'sports']],
        ['Football', 'Professional Football Kit', 'sports', '1553778263-73a83bab9b0c', ['football', 'sports', 'jersey', 'boots']],
        ['Auto Parts', 'Vehicle Maintenance Kit', 'automotive', '1487754180451-c456f719a1fc', ['car', 'auto', 'vehicle', 'maintenance']],
        ['Motorcycles', 'Motorcycle Rider Safety Kit', 'automotive', '1558981806-ec527fa84c39', ['motorcycle', 'helmet', 'rider', 'safety']],
        ['Books', 'Nigerian Contemporary Fiction Set', 'books', '1495446815901-a7297e633e8d', ['books', 'fiction', 'nigerian', 'reading']],
        ['Stationery', 'School Stationery Bundle', 'books', '1455390582262-044cdead277a', ['school', 'stationery', 'books', 'students']],
        ['Agriculture', 'Small Farm Starter Tools', 'agriculture', '1500937386664-56d1dfef3854', ['farm', 'agriculture', 'tools', 'garden']],
        ['Tools', 'Professional Hand Tool Box', 'tools', '1581783898377-1c85bf937427', ['tools', 'repair', 'hardware', 'professional']],
        ['Office', 'Ergonomic Office Chair', 'office', '1505843490538-5133c6c7d0e1', ['office', 'chair', 'work', 'ergonomic']],
        ['Watches', 'Classic Everyday Wristwatch', 'accessories', '1523275335684-37898b6baf30', ['watch', 'wristwatch', 'accessories', 'gift']],
        ['Jewellery', 'Gold Plated Jewellery Set', 'accessories', '1515562141207-7a88fb7ce338', ['jewellery', 'gold', 'accessories', 'occasion']],
        ['Gaming', 'Wireless Gaming Controller', 'electronics', '1606144042614-b2417e99c4e3', ['gaming', 'controller', 'electronics', 'wireless']],
        ['Appliances', 'Energy Saving Standing Fan', 'home', '1585771724684-38269d6639fd', ['fan', 'appliance', 'home', 'electricity']],
        ['Cleaning', 'Household Cleaning Bundle', 'home', '1585421514284-efb74c2b69ba', ['cleaning', 'household', 'home', 'hygiene']],
        ['Pet Supplies', 'Pet Care Essentials Pack', 'family', '1601758125946-6ec2ef64daf8', ['pet', 'dog', 'cat', 'care']],
    ];

    public function handle(): int
    {
        $vendorCount = max(1, (int) $this->option('vendors'));
        $productsPerVendor = max(1, (int) $this->option('products-per-vendor'));
        $path = $this->option('path') ?: database_path('data/seeder.json');
        $seed = (int) $this->option('seed');

        if (! is_dir(dirname($path)) && ! mkdir(dirname($path), 0777, true) && ! is_dir(dirname($path))) {
            throw new RuntimeException('Unable to create output directory: '.dirname($path));
        }

        $handle = fopen($path, 'wb');
        throw_unless($handle, RuntimeException::class, "Unable to open $path for writing.");

        $productCount = $vendorCount * $productsPerVendor;
        fwrite($handle, "{\n");
        fwrite($handle, '"metadata":'.json_encode([
            'version' => 1,
            'seed' => $seed,
            'vendors_count' => $vendorCount,
            'products_per_vendor' => $productsPerVendor,
            'products_count' => $productCount,
            'context' => 'Nigerian multi-vendor ecommerce marketplace',
        ], JSON_UNESCAPED_SLASHES).",\n");
        fwrite($handle, "\"vendors\":[\n");

        for ($vendor = 1; $vendor <= $vendorCount; $vendor++) {
            $record = $this->vendor($vendor, $seed);
            fwrite($handle, json_encode($record, JSON_UNESCAPED_SLASHES).($vendor < $vendorCount ? ",\n" : "\n"));
        }

        fwrite($handle, "],\n\"products\":[\n");
        $bar = $this->output->createProgressBar($productCount);
        for ($vendor = 1; $vendor <= $vendorCount; $vendor++) {
            for ($product = 1; $product <= $productsPerVendor; $product++) {
                $position = (($vendor - 1) * $productsPerVendor) + $product;
                fwrite($handle, json_encode($this->product($vendor, $product, $seed), JSON_UNESCAPED_SLASHES).($position < $productCount ? ",\n" : "\n"));
                $bar->advance();
            }
        }
        fwrite($handle, "]\n}\n");
        fclose($handle);
        $bar->finish();
        $this->newLine(2);
        $this->components->info(number_format($vendorCount).' vendors and '.number_format($productCount)." products written to $path.");
        $this->line('Size: '.$this->formatBytes(filesize($path)));

        return self::SUCCESS;
    }

    private function vendor(int $number, int $seed): array
    {
        $prefix = self::VENDOR_PREFIXES[($number + $seed) % count(self::VENDOR_PREFIXES)];
        $suffix = self::VENDOR_SUFFIXES[(intdiv($number, count(self::VENDOR_PREFIXES)) + $seed) % count(self::VENDOR_SUFFIXES)];
        $name = "$prefix $suffix ".str_pad((string) $number, 3, '0', STR_PAD_LEFT);
        $location = self::LOCATIONS[($number * 7 + $seed) % count(self::LOCATIONS)];

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => "$name is an independent Nigerian marketplace vendor serving customers from $location with carefully selected local and imported products.",
            'logo_url' => strtoupper(substr($prefix, 0, 1).substr($suffix, 0, 1)),
            'banner_url' => $this->unsplashUrl('1555529669-e69e7aa0ba9a', 1200, 80, $number),
            'location' => $location,
            'is_verified' => $number % 5 !== 0,
            'rating' => round(4 + (($number * 13) % 10) / 10, 1),
            'fulfillment_days' => 1 + (($number + $seed) % 6),
            'is_active' => true,
        ];
    }

    private function product(int $vendor, int $product, int $seed): array
    {
        $category = self::CATEGORIES[(($vendor * 11) + $product + $seed) % count(self::CATEGORIES)];
        [$categoryName, $baseName, $department, $photoId, $terms] = $category;
        $vendorSlug = $this->vendor($vendor, $seed)['slug'];
        $location = self::LOCATIONS[($vendor * 7 + $seed) % count(self::LOCATIONS)];
        $namer = app(MarketplaceProductNamer::class);
        $name = $namer->name($categoryName, $baseName, $department, $vendor, $product, $seed);

        return [
            'vendor_slug' => $vendorSlug,
            'name' => $name,
            'category' => $categoryName,
            'description' => $namer->description($name, $categoryName, $department, $location),
            'image_url' => $this->unsplashUrl($photoId, 900, 80, ($vendor * 1000) + $product),
            'price_kobo' => 150000 + ((($vendor * 7919) + ($product * 3571) + $seed) % 85000000),
            'search_terms' => [...$terms, $department, ...$this->nameTokens($name), $location],
            'seller_sku' => 'V'.str_pad((string) $vendor, 4, '0', STR_PAD_LEFT).'-P'.str_pad((string) $product, 4, '0', STR_PAD_LEFT),
            'inventory_count' => 1 + (($vendor * 17 + $product * 29 + $seed) % 250),
            'is_active' => ($product % 97) !== 0,
        ];
    }

    private function formatBytes(int $bytes): string
    {
        return number_format($bytes / 1024 / 1024, 1).' MB';
    }

    private function unsplashUrl(string $photoId, int $width, int $quality, int $signature): string
    {
        return "https://images.unsplash.com/photo-$photoId?ixlib=rb-4.0.3&auto=format&fit=crop&w=$width&q=$quality&sig=$signature";
    }

    private function nameTokens(string $name): array
    {
        return collect(preg_split('/[^a-z0-9]+/', Str::lower($name), -1, PREG_SPLIT_NO_EMPTY))
            ->filter(fn (string $token): bool => strlen($token) > 2)
            ->unique()
            ->take(8)
            ->values()
            ->all();
    }
}
