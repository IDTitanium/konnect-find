<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $vendors = [
            ['Aso Lagos', 'aso-lagos', 'Contemporary Nigerian occasionwear and handcrafted accessories.', 'AL', 'https://images.unsplash.com/photo-1539109136881-3be0616acf4b?auto=format&fit=crop&w=1200&q=80', 'Lagos', true, 4.9, 2],
            ['Northstar Menswear', 'northstar-menswear', 'Quiet luxury and sharp essentials for modern Nigerian men.', 'NM', 'https://images.unsplash.com/photo-1490481651871-ab68de25d43d?auto=format&fit=crop&w=1200&q=80', 'Abuja', true, 4.8, 3],
            ['Stride NG', 'stride-ng', 'Everyday footwear built for movement, comfort, and Lagos streets.', 'SN', 'https://images.unsplash.com/photo-1549298916-b41d501d3772?auto=format&fit=crop&w=1200&q=80', 'Lagos', true, 4.7, 2],
            ['BrightHome Energy', 'brighthome-energy', 'Reliable backup power and solar solutions for homes and businesses.', 'BE', 'https://images.unsplash.com/photo-1473341304170-971dccb5ac1e?auto=format&fit=crop&w=1200&q=80', 'Port Harcourt', true, 4.9, 4],
            ['Everyday Market', 'everyday-market', 'Useful, well-designed essentials for work, school, and home.', 'EM', 'https://images.unsplash.com/photo-1555529669-e69e7aa0ba9a?auto=format&fit=crop&w=1200&q=80', 'Ibadan', false, 4.5, 3],
        ];

        foreach ($vendors as [$name, $slug, $description, $logoUrl, $bannerUrl, $location, $isVerified, $rating, $fulfillmentDays]) {
            Vendor::updateOrCreate(['slug' => $slug], [
                'name' => $name,
                'description' => $description,
                'logo_url' => $logoUrl,
                'banner_url' => $bannerUrl,
                'location' => $location,
                'is_verified' => $isVerified,
                'rating' => $rating,
                'fulfillment_days' => $fulfillmentDays,
                'is_active' => true,
            ]);
        }

        $products = [
            ['aso-lagos', 'Adire Silk Occasion Dress', 'Fashion', 'Elegant indigo adire dress for weddings, parties and special occasions.', 'https://images.unsplash.com/photo-1595777457583-95e059d581b8?auto=format&fit=crop&w=900&q=80', 4850000, ['owambe', 'traditional', 'classy', 'blue', 'women']],
            ['aso-lagos', 'Premium Ankara Two-Piece', 'Fashion', 'Bold but balanced Ankara set tailored for modern celebrations.', 'https://images.unsplash.com/photo-1583391733956-6c78276477e2?auto=format&fit=crop&w=900&q=80', 3200000, ['ankara', 'party', 'traditional', 'colourful', 'women']],
            ['northstar-menswear', 'Classic Black Kaftan', 'Fashion', 'Understated formal kaftan suitable for ceremonies and evening events.', 'https://images.unsplash.com/photo-1617137968427-85924c800a22?auto=format&fit=crop&w=900&q=80', 2750000, ['burial', 'formal', 'classy', 'black', 'men']],
            ['aso-lagos', 'Handwoven Aso Oke Tote', 'Accessories', 'Structured statement tote made with handwoven Aso Oke fabric.', 'https://images.unsplash.com/photo-1584917865442-de89df76afd3?auto=format&fit=crop&w=900&q=80', 1850000, ['bag', 'handbag', 'traditional', 'woven']],
            ['stride-ng', 'Everyday Leather Loafers', 'Footwear', 'Comfortable leather loafers for office days and smart occasions.', 'https://images.unsplash.com/photo-1533867617858-e7b97e060509?auto=format&fit=crop&w=900&q=80', 2400000, ['shoes', 'office', 'formal', 'men']],
            ['stride-ng', 'Street Runner Sneakers', 'Footwear', 'Cushioned trainers built for long commutes and everyday movement.', 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=900&q=80', 2950000, ['kicks', 'sneakers', 'trainers', 'durable']],
            ['everyday-market', 'Durable Kids School Backpack', 'Bags', 'Water-resistant school bag with reinforced straps and roomy pockets.', 'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?auto=format&fit=crop&w=900&q=80', 1250000, ['pikin', 'children', 'school', 'durable', 'bag']],
            ['brighthome-energy', '2000VA Smart Inverter', 'Power', 'Reliable backup power solution for home offices and essential appliances.', 'https://images.unsplash.com/photo-1620714223084-8fcacc6dfd8d?auto=format&fit=crop&w=900&q=80', 24500000, ['power', 'inverter', 'electricity', 'heavy-duty']],
            ['brighthome-energy', 'Portable Solar Generator', 'Power', 'Quiet portable solar power station for homes, shops and outdoor use.', 'https://images.unsplash.com/photo-1508514177221-188b1cf16e9d?auto=format&fit=crop&w=900&q=80', 38000000, ['power', 'generator', 'solar', 'heavy-duty']],
            ['everyday-market', 'Noise-Cancelling Headphones', 'Electronics', 'Wireless over-ear headphones with deep sound and all-day comfort.', 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=900&q=80', 7800000, ['music', 'wireless', 'headset', 'electronics']],
            ['everyday-market', 'Minimal Work Laptop Bag', 'Bags', 'Padded professional laptop bag with a clean, lightweight profile.', 'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?auto=format&fit=crop&w=900&q=80', 2100000, ['office', 'work', 'professional', 'bag']],
            ['everyday-market', 'Ceramic Dinner Set', 'Home', 'Contemporary stoneware dinner set for everyday meals and hosting.', 'https://images.unsplash.com/photo-1603199506016-b9a594b593c0?auto=format&fit=crop&w=900&q=80', 3500000, ['kitchen', 'plates', 'dining', 'home']],
        ];

        foreach ($products as $index => [$vendorSlug, $name, $category, $description, $imageUrl, $priceKobo, $searchTerms]) {
            Product::updateOrCreate(['name' => $name], [
                'vendor_id' => Vendor::where('slug', $vendorSlug)->value('id'),
                'category' => $category,
                'description' => $description,
                'image_url' => $imageUrl,
                'price_kobo' => $priceKobo,
                'search_terms' => $searchTerms,
                'seller_sku' => strtoupper(substr(str_replace('-', '', $vendorSlug), 0, 4)).'-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                'inventory_count' => 8 + ($index * 7) % 42,
                'is_active' => true,
                'text_embedding' => null,
                'image_embedding' => null,
                'embeddings_indexed_at' => null,
            ]);
        }
    }
}
