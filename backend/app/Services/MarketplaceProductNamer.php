<?php

namespace App\Services;

use Illuminate\Support\Str;

class MarketplaceProductNamer
{
    private const BRANDS = [
        'fashion' => ['Zuri', 'Abeni', 'Oriire', 'Eko Thread', 'Kente Lane', 'Adire House', 'Mode Lagos', 'Arewa Stitch'],
        'footwear' => ['StridePro', 'KicksLab', 'Urban Sole', 'TrekStep', 'Lagos Walk', 'NovaStride', 'SoleMate', 'RoadRunner'],
        'bags' => ['CarryWell', 'Tote & Co', 'PackMate', 'UrbanCarry', 'Aso Tote', 'DailyPack', 'Lagos Leather', 'Karry'],
        'electronics' => ['MobiCore', 'SoundMax', 'NovaTech', 'PrimeWave', 'VoltEdge', 'ClearTone', 'ByteLine', 'SmartHub'],
        'power' => ['BrightVolt', 'SolarMax', 'PowerHaus', 'VoltEdge', 'GridBack', 'SunPort', 'Inverta', 'EnergiPro'],
        'home' => ['CasaLuxe', 'HomeNest', 'KitchenPro', 'DineWell', 'UrbanHome', 'Noble Living', 'CleanHaus', 'ComfortBay'],
        'beauty' => ['SheaGlow', 'NaturaCare', 'GlowHaus', 'Amani Beauty', 'Ori Organics', 'SoftSkin', 'CurlKind', 'ScentLane'],
        'family' => ['TinySteps', 'MamaCare', 'HomePals', 'KidNest', 'BabyLoop', 'FamilyFirst', 'PetHaven', 'CareBox'],
        'food' => ['Mama Gold', 'Naija Pantry', 'Market Fresh', 'Oja Select', 'TasteWell', 'Golden Bowl', 'Farm Basket', 'Daily Grain'],
        'sports' => ['FitCore', 'PlayPro', 'NaijaFit', 'GoalLine', 'ActiveHub', 'GymMate', 'Sportiva', 'FlexZone'],
        'automotive' => ['MotoCare', 'RoadKing', 'AutoFix', 'DriveSure', 'MechPro', 'SafeRide', 'TorqueLine', 'UrbanMoto'],
        'books' => ['ReadWell', 'StudyMate', 'PageHouse', 'BookNest', 'CampusLine', 'LitBox', 'ScholarPack', 'InkRoad'],
        'agriculture' => ['FarmPro', 'AgroMate', 'GreenField', 'GrowWell', 'HarvestLine', 'SoilCare', 'FarmNest', 'CropKing'],
        'tools' => ['ToolPro', 'HandyMate', 'BuildLine', 'FixRight', 'WorkBench', 'CraftMax', 'IronKit', 'MechTool'],
        'office' => ['DeskWell', 'WorkNest', 'ErgoLine', 'OfficePro', 'TaskMate', 'ChairHaus', 'FocusDesk', 'UrbanOffice'],
        'accessories' => ['GoldLane', 'TimeHaus', 'StyleBox', 'LuxeLoop', 'WristCo', 'GemNest', 'Occasion Co', 'Urban Accent'],
        'general' => ['Konnect', 'MarketPro', 'DailyBest', 'PrimeSelect', 'Urban Choice', 'ValueLine', 'NairaSmart', 'ShopWell'],
    ];

    private const PRODUCT_PARTS = [
        'Women Fashion' => [
            'nouns' => ['Ankara Midi Dress', 'Adire Wrap Dress', 'Bubu Gown', 'Pleated Occasion Dress'],
            'features' => ['lined', 'tailored', 'flare-sleeve', 'belted', 'modest-fit', 'ready-to-wear'],
            'variants' => ['Indigo Bloom', 'Wine Floral', 'Emerald Geo', 'Coral Motif', 'Gold Accent', 'Navy Swirl'],
            'specs' => ['Size 8-16', 'Size 10-18', 'Free Size', 'Midi Length', 'Cotton Blend', 'Silk Finish'],
        ],
        'Men Fashion' => [
            'nouns' => ['Senator Kaftan Set', 'Native Wear Set', 'Long Sleeve Kaftan', 'Two-Piece Agbada Inner'],
            'features' => ['embroidered', 'slim-cut', 'breathable', 'ceremony-ready', 'soft-collar', 'plain-front'],
            'variants' => ['Charcoal', 'Navy Blue', 'Champagne', 'Forest Green', 'Black Thread', 'Cream Stitch'],
            'specs' => ['M-XXL', 'L-XXXL', 'Cotton Blend', 'Machine Embroidery', 'Two-Piece', 'Ready to Wear'],
        ],
        'Traditional Fabrics' => [
            'nouns' => ['Aso Oke Bundle', 'Ankara Fabric Pack', 'Adire Yard Set', 'George Wrapper Bundle'],
            'features' => ['handwoven', 'ceremony-grade', 'soft-touch', 'colourfast', 'wedding-ready', 'premium-weave'],
            'variants' => ['Wine Gold', 'Royal Blue', 'Olive Stripe', 'Ivory Gold', 'Purple Bloom', 'Teal Pattern'],
            'specs' => ['6 Yards', '5 Yards', 'Headtie Included', 'Double Width', 'Family Set', 'Party Pack'],
        ],
        'Footwear' => [
            'nouns' => ['Leather Loafers', 'Office Derby Shoes', 'Soft Sole Moccasins', 'Smart Casual Shoes'],
            'features' => ['cushioned', 'polished', 'all-day', 'wide-fit', 'anti-slip', 'hand-finished'],
            'variants' => ['Black', 'Tan Brown', 'Coffee', 'Oxblood', 'Navy', 'Dark Walnut'],
            'specs' => ['EU 40-45', 'EU 39-44', 'Leather Upper', 'Rubber Sole', 'Office Fit', 'Weekend Fit'],
        ],
        'Sneakers' => [
            'nouns' => ['Runner Sneakers', 'Street Trainers', 'Low-Top Sneakers', 'Commuter Kicks'],
            'features' => ['breathable', 'lightweight', 'cushioned', 'grip-sole', 'washable', 'daily-wear'],
            'variants' => ['White Green', 'Black Red', 'Grey Lime', 'Navy Cream', 'Sand Orange', 'Mono Black'],
            'specs' => ['EU 40-46', 'Memory Foam', 'Lace-Up', 'Mesh Upper', 'Road Grip', 'Gym Ready'],
        ],
        'Bags' => [
            'nouns' => ['School Backpack', 'Laptop Backpack', 'Travel Daypack', 'Student Rucksack'],
            'features' => ['water-resistant', 'reinforced', 'padded', 'double-zip', 'lightweight', 'roomy'],
            'variants' => ['Navy', 'Black Grey', 'Wine', 'Olive', 'Royal Blue', 'Charcoal'],
            'specs' => ['18L', '22L', '15-inch Laptop', 'Three Compartments', 'Kids Fit', 'Senior School'],
        ],
        'Handbags' => [
            'nouns' => ['Structured Handbag', 'Aso Oke Tote', 'Mini Occasion Bag', 'Everyday Shoulder Bag'],
            'features' => ['lined', 'zip-top', 'woven', 'chain-strap', 'boxy', 'soft-handle'],
            'variants' => ['Gold Brown', 'Indigo Weave', 'Black Patent', 'Wine Velvet', 'Cream Bead', 'Green Pattern'],
            'specs' => ['Medium', 'Compact', 'Detachable Strap', 'Inner Pocket', 'Party Size', 'Work Size'],
        ],
        'Phones' => [
            'nouns' => ['Dual SIM Android Phone', '4G Smartphone', 'Budget Android Phone', 'Large Battery Smartphone'],
            'features' => ['fast-charge', 'face-unlock', 'long-battery', 'clear-camera', 'dual-camera', 'fingerprint'],
            'variants' => ['Midnight Black', 'Sky Blue', 'Emerald', 'Graphite', 'Champagne', 'Ice Silver'],
            'specs' => ['4GB/64GB', '6GB/128GB', '5000mAh', '128GB Storage', '6.6-inch', 'Android 14'],
        ],
        'Laptops' => [
            'nouns' => ['Work Laptop', 'Student Laptop', 'Business Notebook', 'Slim Ultrabook'],
            'features' => ['lightweight', 'SSD', 'backlit-keyboard', 'HD webcam', 'long-battery', 'office-ready'],
            'variants' => ['Silver', 'Graphite', 'Matte Black', 'Navy', 'Space Grey', 'Champagne'],
            'specs' => ['8GB/256GB SSD', '16GB/512GB SSD', '14-inch', '15.6-inch', 'Core i5 Class', 'Ryzen 5 Class'],
        ],
        'Audio' => [
            'nouns' => ['Noise Cancelling Headphones', 'Wireless Earbuds', 'Bluetooth Headset', 'Over-Ear Headphones'],
            'features' => ['deep-bass', 'low-latency', 'sweat-resistant', 'foldable', 'clear-call', 'long-play'],
            'variants' => ['Black', 'Pearl White', 'Navy', 'Rose Gold', 'Graphite', 'Olive'],
            'specs' => ['30hr Battery', 'USB-C', 'ANC', 'Gaming Mode', 'Dual Mic', 'Fast Charge'],
        ],
        'Power' => [
            'nouns' => ['Pure Sine Inverter', 'Home Backup Inverter', 'Smart Inverter System', 'Inverter and Battery Kit'],
            'features' => ['quiet', 'load-protected', 'solar-ready', 'LCD-display', 'heavy-duty', 'appliance-safe'],
            'variants' => ['Home Office', 'Mini Flat', 'Shop Backup', 'Family Use', 'Essential Load', 'Night Backup'],
            'specs' => ['1.5kVA', '2kVA', '3.5kVA', '24V', '48V', 'Battery Ready'],
        ],
        'Solar' => [
            'nouns' => ['Portable Solar Generator', 'Solar Power Station', 'Rechargeable Solar Kit', 'Mini Solar Backup'],
            'features' => ['portable', 'silent', 'fast-charge', 'LED-display', 'camp-ready', 'shop-ready'],
            'variants' => ['Outdoor', 'Home Office', 'Market Stall', 'Travel', 'Essential Load', 'Weekend'],
            'specs' => ['300W', '500W', '800W', 'LiFePO4', 'AC Output', 'USB-C PD'],
        ],
        'Generators' => [
            'nouns' => ['Petrol Generator', 'Fuel Efficient Generator', 'Silent Generator', 'Manual Start Generator'],
            'features' => ['copper-coil', 'low-noise', 'rugged', 'fuel-saving', 'easy-start', 'heavy-duty'],
            'variants' => ['Home Use', 'Shop Use', 'Outdoor Work', 'Essential Load', 'Weekend Backup', 'Site Work'],
            'specs' => ['2.5kVA', '3.5kVA', '5kVA', 'Key Start', 'Manual Start', 'AVR Protected'],
        ],
        'Kitchen' => [
            'nouns' => ['Non-Stick Cookware Set', 'Granite Pot Set', 'Stainless Cooking Set', 'Kitchen Starter Set'],
            'features' => ['scratch-resistant', 'easy-clean', 'family-size', 'induction-ready', 'heat-proof', 'stackable'],
            'variants' => ['Black Marble', 'Rose Gold', 'Grey Stone', 'Red Finish', 'Cream Lid', 'Copper Accent'],
            'specs' => ['6 Pieces', '8 Pieces', '10 Pieces', 'Glass Lids', 'Wood Handles', 'Gift Box'],
        ],
        'Dining' => [
            'nouns' => ['Ceramic Dinner Set', 'Stoneware Plate Set', 'Melamine Dining Set', 'Breakfast Bowl Set'],
            'features' => ['chip-resistant', 'host-ready', 'microwave-safe', 'matte-finish', 'family-size', 'easy-stack'],
            'variants' => ['Ivory', 'Blue Rim', 'Sage Green', 'Charcoal', 'White Gold', 'Terracotta'],
            'specs' => ['12 Pieces', '16 Pieces', '24 Pieces', 'Service for 4', 'Service for 6', 'Bowl Included'],
        ],
        'Furniture' => [
            'nouns' => ['Living Room Sofa', 'Compact Sofa', 'Lounge Chair Set', 'Apartment Couch'],
            'features' => ['deep-seat', 'easy-clean', 'wood-frame', 'space-saving', 'plush', 'firm-support'],
            'variants' => ['Grey Linen', 'Forest Green', 'Tan Leatherette', 'Navy Fabric', 'Cream Boucle', 'Brown Suede'],
            'specs' => ['2-Seater', '3-Seater', 'L-Shape', 'Apartment Size', 'Throw Pillows', 'Removable Covers'],
        ],
        'Home Decor' => [
            'nouns' => ['Woven Basket', 'Wall Art Set', 'Decor Tray', 'Table Vase Set'],
            'features' => ['handcrafted', 'natural-fibre', 'minimal', 'boho', 'statement', 'neutral-tone'],
            'variants' => ['Raffia', 'Black Natural', 'Earth Tone', 'Indigo Trim', 'Palm Weave', 'Clay Finish'],
            'specs' => ['Set of 2', 'Set of 3', 'Large', 'Medium', 'Tabletop', 'Wall Mount'],
        ],
        'Beauty' => [
            'nouns' => ['Shea Butter Skincare Set', 'Body Glow Set', 'Face Care Bundle', 'Natural Skincare Kit'],
            'features' => ['moisturising', 'fragrance-free', 'glow-boosting', 'sensitive-skin', 'natural-oil', 'daily-care'],
            'variants' => ['Honey Oat', 'Cocoa Shea', 'Aloe Mint', 'Rose Water', 'Coconut Milk', 'Turmeric Glow'],
            'specs' => ['3-Step', 'Travel Size', 'Family Size', '250ml', 'Body and Face', 'Gift Pack'],
        ],
        'Hair Care' => [
            'nouns' => ['Natural Hair Care Bundle', 'Curl Care Set', 'Moisture Wash Day Kit', 'Protective Style Kit'],
            'features' => ['sulfate-free', 'curl-defining', 'moisture-rich', 'scalp-care', 'leave-in', 'softening'],
            'variants' => ['Coconut Shea', 'Aloe Castor', 'Mint Tea Tree', 'Hibiscus Curl', 'Avocado Honey', 'Black Soap'],
            'specs' => ['4 Pieces', 'Wash Day', 'Kids Friendly', '500ml', 'Leave-In Included', 'Deep Conditioner'],
        ],
        'Fragrances' => [
            'nouns' => ['Eau de Parfum', 'Body Mist Set', 'Long Lasting Perfume', 'Roll-On Fragrance'],
            'features' => ['long-wear', 'soft-spice', 'fresh', 'warm-amber', 'oud-blend', 'office-safe'],
            'variants' => ['Vanilla Oud', 'Citrus Musk', 'Amber Rose', 'Fresh Linen', 'Sweet Tobacco', 'Ocean Mint'],
            'specs' => ['50ml', '100ml', 'Gift Box', 'Unisex', 'Travel Spray', 'Oil Perfume'],
        ],
        'Baby Products' => [
            'nouns' => ['Baby Essentials Set', 'Newborn Care Box', 'Baby Bath Bundle', 'Diaper Bag Starter Set'],
            'features' => ['gentle', 'newborn-safe', 'soft-cotton', 'travel-ready', 'washable', 'hypoallergenic'],
            'variants' => ['Blue', 'Pink', 'Neutral Cream', 'Animal Print', 'Cloud Theme', 'Mint'],
            'specs' => ['0-6 Months', '6 Pieces', 'Bath Time', 'Hospital Bag', 'Gift Set', 'Cotton Pack'],
        ],
        'Groceries' => [
            'nouns' => ['Nigerian Rice Bag', 'Parboiled Rice', 'Ofada Rice Pack', 'Long Grain Rice'],
            'features' => ['stone-free', 'premium-grade', 'family-size', 'market-fresh', 'clean-sorted', 'locally-packed'],
            'variants' => ['Ofada Blend', 'Long Grain', 'Golden Sella', 'Local Select', 'Aroso', 'Everyday Pot'],
            'specs' => ['5kg', '10kg', '25kg', '50kg', 'Family Pack', 'Resealable'],
        ],
        'Food Staples' => [
            'nouns' => ['Beans and Garri Pack', 'Pantry Staples Box', 'Family Food Bundle', 'Weekly Food Pack'],
            'features' => ['clean-sorted', 'budget-friendly', 'family-size', 'market-fresh', 'quick-cook', 'bulk-value'],
            'variants' => ['Honey Beans', 'White Garri', 'Ijebu Garri', 'Brown Beans', 'Mixed Staples', 'Soup Starter'],
            'specs' => ['5kg', '10kg', 'Combo Pack', 'Monthly Pack', 'Family Pack', 'Resealable'],
        ],
        'Spices' => [
            'nouns' => ['Cooking Spice Box', 'Soup Spice Set', 'Jollof Seasoning Pack', 'Pepper Mix Bundle'],
            'features' => ['aromatic', 'fresh-ground', 'no-MSG', 'party-pot', 'small-batch', 'kitchen-ready'],
            'variants' => ['Jollof Blend', 'Suya Spice', 'Pepper Soup', 'Egusi Helper', 'Stew Base', 'Grill Mix'],
            'specs' => ['6 Jars', '10 Sachets', 'Family Pack', 'Refill Pack', 'Gift Box', 'Restaurant Size'],
        ],
        'Drinks' => [
            'nouns' => ['Zobo Drink Pack', 'Hibiscus Drink Mix', 'Ginger Juice Pack', 'Fruit Drink Carton'],
            'features' => ['refreshing', 'low-sugar', 'party-ready', 'chilled-serve', 'natural-spice', 'family-size'],
            'variants' => ['Ginger Zobo', 'Pineapple Zobo', 'Clove Hibiscus', 'Lemon Ginger', 'Mint Zobo', 'Classic Red'],
            'specs' => ['6 Bottles', '12 Bottles', '500ml', '1L', 'Concentrate', 'No Preservatives'],
        ],
        'Fitness' => [
            'nouns' => ['Home Workout Set', 'Resistance Band Kit', 'Adjustable Dumbbell Set', 'Yoga Mat Bundle'],
            'features' => ['compact', 'sweat-proof', 'beginner-friendly', 'home-gym', 'portable', 'anti-slip'],
            'variants' => ['Black Orange', 'Purple Grey', 'Blue', 'Neon Green', 'Matte Black', 'Pink Grey'],
            'specs' => ['5kg Pair', '10kg Pair', '5 Bands', '6mm Mat', 'Starter Kit', 'Carry Bag'],
        ],
        'Football' => [
            'nouns' => ['Football Kit', 'Training Jersey Set', 'Match Ball', 'Football Boot Set'],
            'features' => ['breathable', 'quick-dry', 'match-ready', 'grass-grip', 'academy-grade', 'lightweight'],
            'variants' => ['Green White', 'Red Black', 'Blue Yellow', 'Home Kit', 'Away Kit', 'Training Black'],
            'specs' => ['Size M-XXL', 'Size 5 Ball', 'Boot EU 40-45', 'Socks Included', 'Full Kit', 'Youth Size'],
        ],
        'Auto Parts' => [
            'nouns' => ['Vehicle Maintenance Kit', 'Car Care Bundle', 'Engine Service Pack', 'Emergency Road Kit'],
            'features' => ['mechanic-approved', 'all-weather', 'heavy-duty', 'compact', 'road-ready', 'easy-store'],
            'variants' => ['Toyota Fit', 'Universal', 'Salon Car', 'SUV', 'Commercial Use', 'Weekend Service'],
            'specs' => ['5 Pieces', 'Oil Funnel', 'Jump Cable', 'Tyre Gauge', 'Tool Pouch', 'First Aid Included'],
        ],
        'Motorcycles' => [
            'nouns' => ['Rider Safety Kit', 'Motorcycle Helmet Set', 'Dispatch Rider Kit', 'Bike Maintenance Pack'],
            'features' => ['impact-rated', 'reflective', 'rain-ready', 'breathable', 'lightweight', 'road-safe'],
            'variants' => ['Matte Black', 'Red Stripe', 'Blue White', 'Hi-Vis', 'Grey', 'Orange Accent'],
            'specs' => ['Helmet Included', 'Gloves Included', 'M-XL', 'Rain Cover', 'Dispatch Ready', 'Universal Fit'],
        ],
        'Books' => [
            'nouns' => ['Contemporary Fiction Set', 'Nigerian Literature Box', 'Paperback Reading Set', 'Weekend Book Bundle'],
            'features' => ['paperback', 'book-club', 'new-release', 'gift-ready', 'easy-read', 'curated'],
            'variants' => ['Lagos Stories', 'African Voices', 'Campus Picks', 'Family Drama', 'New Writers', 'Classic Mix'],
            'specs' => ['3 Books', '5 Books', 'Paperback', 'Young Adult', 'Adult Fiction', 'Gift Wrap'],
        ],
        'Stationery' => [
            'nouns' => ['School Stationery Bundle', 'Student Writing Set', 'Exam Prep Pack', 'Desk Supply Kit'],
            'features' => ['classroom-ready', 'budget-pack', 'durable', 'neat-writing', 'teacher-approved', 'term-ready'],
            'variants' => ['Blue Theme', 'Primary School', 'Secondary School', 'Exam Season', 'Math Set', 'Art Add-On'],
            'specs' => ['20 Pieces', '40 Pieces', 'Notebooks Included', 'Pen Pack', 'Math Set', 'Back-to-School'],
        ],
        'Agriculture' => [
            'nouns' => ['Farm Starter Tools', 'Garden Tool Set', 'Small Farm Kit', 'Planting Starter Pack'],
            'features' => ['rust-resistant', 'field-ready', 'easy-grip', 'compact', 'durable', 'smallholder'],
            'variants' => ['Vegetable Garden', 'Maize Plot', 'Backyard Farm', 'Nursery Work', 'Dry Season', 'Rainy Season'],
            'specs' => ['5 Pieces', '8 Pieces', 'Gloves Included', 'Seed Tray', 'Hand Tools', 'Beginner Kit'],
        ],
        'Tools' => [
            'nouns' => ['Hand Tool Box', 'Repair Tool Set', 'Home Fix Kit', 'Mechanic Tool Case'],
            'features' => ['chrome-vanadium', 'heavy-duty', 'anti-rust', 'rubber-grip', 'compact-case', 'professional'],
            'variants' => ['Black Case', 'Red Case', 'Home Repair', 'Workshop', 'Car Boot', 'Site Work'],
            'specs' => ['32 Pieces', '45 Pieces', 'Socket Set', 'Screwdriver Set', 'Measuring Tape', 'Spanner Set'],
        ],
        'Office' => [
            'nouns' => ['Ergonomic Office Chair', 'Task Chair', 'Mesh Back Chair', 'Work Desk Chair'],
            'features' => ['lumbar-support', 'height-adjustable', 'breathable', 'swivel', 'tilt-lock', 'work-from-home'],
            'variants' => ['Black Mesh', 'Grey Fabric', 'Navy', 'Brown Leatherette', 'White Frame', 'Green Seat'],
            'specs' => ['Adjustable Arms', '120kg Rated', 'Headrest', 'Footrest', 'Nylon Wheels', 'Assembly Kit'],
        ],
        'Watches' => [
            'nouns' => ['Everyday Wristwatch', 'Classic Dress Watch', 'Smart Casual Watch', 'Leather Strap Watch'],
            'features' => ['water-resistant', 'date-window', 'minimal-dial', 'gift-ready', 'slim-case', 'scratch-resistant'],
            'variants' => ['Black Tan', 'Silver Blue', 'Gold Brown', 'Rose Gold', 'Gunmetal', 'Navy Strap'],
            'specs' => ['40mm', '42mm', 'Leather Strap', 'Steel Strap', 'Quartz', 'Gift Box'],
        ],
        'Jewellery' => [
            'nouns' => ['Jewellery Set', 'Gold Plated Necklace Set', 'Occasion Earring Set', 'Bracelet and Chain Set'],
            'features' => ['hypoallergenic', 'occasion-ready', 'polished', 'layered', 'statement', 'minimal'],
            'variants' => ['Gold', 'Rose Gold', 'Pearl Accent', 'Emerald Stone', 'Crystal', 'Two-Tone'],
            'specs' => ['3 Pieces', '5 Pieces', 'Gift Box', 'Adjustable Chain', 'Stud Earrings', 'Party Set'],
        ],
        'Gaming' => [
            'nouns' => ['Wireless Gaming Controller', 'Gamepad', 'Mobile Gaming Pad', 'Console Controller'],
            'features' => ['low-latency', 'dual-vibration', 'long-battery', 'grip-texture', 'USB-C', 'plug-and-play'],
            'variants' => ['Black Blue', 'Red Black', 'White', 'Camo', 'Neon Green', 'Transparent'],
            'specs' => ['Bluetooth', '2.4GHz', 'Android Compatible', 'PC Compatible', 'Rechargeable', 'Turbo Mode'],
        ],
        'Appliances' => [
            'nouns' => ['Standing Fan', 'Rechargeable Fan', 'Energy Saving Fan', 'Oscillating Fan'],
            'features' => ['quiet', 'low-power', 'wide-angle', 'remote-control', 'adjustable-height', 'high-airflow'],
            'variants' => ['White', 'Black', 'Grey', 'Blue Accent', 'Cream', 'Silver'],
            'specs' => ['16-inch', '18-inch', 'Rechargeable', '5 Speed', 'Timer', 'Copper Motor'],
        ],
        'Cleaning' => [
            'nouns' => ['Household Cleaning Bundle', 'Laundry Care Pack', 'Kitchen Cleaning Set', 'Home Hygiene Kit'],
            'features' => ['fresh-scent', 'family-size', 'multi-surface', 'stain-lift', 'gentle', 'value-pack'],
            'variants' => ['Lemon Fresh', 'Lavender', 'Unscented', 'Pine Clean', 'Aloe Fresh', 'Citrus Burst'],
            'specs' => ['5 Items', '10 Items', 'Refill Pack', 'Bathroom Kit', 'Kitchen Kit', 'Monthly Pack'],
        ],
        'Pet Supplies' => [
            'nouns' => ['Pet Care Essentials Pack', 'Dog Grooming Kit', 'Cat Feeding Set', 'Pet Starter Box'],
            'features' => ['washable', 'travel-ready', 'easy-clean', 'gentle-care', 'vet-friendly', 'compact'],
            'variants' => ['Small Breed', 'Medium Breed', 'Cat Kit', 'Puppy Kit', 'Blue', 'Grey'],
            'specs' => ['5 Pieces', 'Bowl Included', 'Brush Included', 'Leash Included', 'Starter Pack', 'Travel Pack'],
        ],
    ];

    public function name(string $categoryName, string $baseName, string $department, int $vendor, int $product, int $seed): string
    {
        $parts = self::PRODUCT_PARTS[$categoryName] ?? [
            'nouns' => [$baseName],
            'features' => ['selected', 'durable', 'everyday', 'premium'],
            'variants' => ['Standard', 'Value Pack', 'Family Size', 'Classic'],
            'specs' => ['Ready to Use', 'Gift Pack', 'Daily Use', 'Compact'],
        ];

        $brand = $this->pick(self::BRANDS[$department] ?? self::BRANDS['general'], $vendor + $seed);
        $feature = $this->titlePart($this->pick($parts['features'], ($vendor * 3) + $product + $seed));
        $noun = $this->pick($parts['nouns'], ($product * 5) + $vendor + $seed);
        $variant = $this->pick($parts['variants'], ($vendor * 7) + ($product * 11) + $seed);
        $spec = $this->pick($parts['specs'], ($vendor * 13) + ($product * 17) + $seed);
        $code = $this->code($categoryName, $vendor, $product, $seed);

        return match ($department) {
            'electronics', 'power', 'automotive', 'tools', 'office' => "$brand $noun $spec - $variant ($code)",
            'food' => "$brand $noun $spec - $variant",
            'fashion', 'footwear', 'bags', 'accessories' => "$brand $feature $noun - $variant, $spec",
            default => "$brand $feature $noun - $variant, $spec",
        };
    }

    public function description(string $name, string $categoryName, string $department, string $location): string
    {
        $context = match ($department) {
            'fashion', 'footwear', 'bags', 'accessories' => 'style, fit, and everyday Nigerian occasions',
            'electronics', 'power' => 'reliable daily use, backup needs, and practical performance',
            'food' => 'family kitchens, market runs, and regular pantry restocking',
            'home', 'office' => 'comfortable homes, small apartments, and busy workdays',
            'beauty', 'family' => 'gentle daily care and gift-ready routines',
            'sports', 'automotive', 'tools', 'agriculture' => 'durable handling and practical outdoor use',
            default => 'everyday shopping needs',
        };

        return "$name is a $categoryName item selected in $location for $context.";
    }

    private function pick(array $values, int $index): string
    {
        return $values[$this->positiveModulo($index, count($values))];
    }

    private function titlePart(string $value): string
    {
        return collect(explode('-', $value))
            ->map(fn (string $part): string => Str::ucfirst($part))
            ->join('-');
    }

    private function code(string $categoryName, int $vendor, int $product, int $seed): string
    {
        $letters = strtoupper(Str::of($categoryName)->replaceMatches('/[^a-zA-Z ]/', '')->explode(' ')
            ->filter()
            ->map(fn (string $word): string => $word[0])
            ->join(''));

        $letters = substr($letters ?: 'KF', 0, 3);
        $number = (($vendor * 37) + ($product * 91) + $seed) % 900 + 100;

        return "$letters-$number";
    }

    private function positiveModulo(int $value, int $divisor): int
    {
        return (($value % $divisor) + $divisor) % $divisor;
    }
}
