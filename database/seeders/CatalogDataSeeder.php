<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\SalonServiceProduct;
use App\Models\Saloon;
use App\Models\SaloonBranch;
use App\Models\Service;
use App\Models\ServiceProduct;
use App\Support\Pos\PosCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Production-ready master catalog: categories, services, products, and
 * service↔product assignments with realistic default pricing (INR).
 *
 * Run alone:
 *   php artisan db:seed --class=CatalogDataSeeder
 *
 * Also invoked from DatabaseSeeder (before DemoDataSeeder).
 *
 * Relationship chain:
 *   Category → Service / Product
 *   ServiceProduct (master link + default_price)
 *   SalonServiceProduct (optional: attach to every active salon branch)
 *
 * Services that do not consume a branded product are linked to the
 * "General Item" product at a single consistent price (same as the
 * service default_price). Salon offerings also get a service-only row
 * (product_id = null) at that same price.
 */
class CatalogDataSeeder extends Seeder
{
    public const GENERAL_ITEM = 'General Item';

    public function run(): void
    {
        $masters = $this->seedMasters();
        $this->attachToExistingSalons($masters['services'], $masters['products']);

        if ($this->command !== null) {
            $this->command->info(sprintf(
                'Catalog seeded: %d categories, %d products, %d services, %d service–product links.',
                count($masters['categories']),
                count($masters['products']),
                count($masters['services']),
                ServiceProduct::query()->count(),
            ));
        }
    }

    /**
     * Seed (or refresh) the global master catalog and return name-keyed maps.
     *
     * @return array{
     *     categories: array<string, Category>,
     *     products: array<string, Product>,
     *     services: array<string, Service>
     * }
     */
    public function seedMasters(): array
    {
        $categories = $this->seedCategories();
        $products = $this->seedProducts($categories);
        $services = $this->seedServices($categories, $products);
        $this->seedRetailService($categories, $services);

        return [
            'categories' => $categories,
            'products' => $products,
            'services' => $services,
        ];
    }

    /**
     * Attach the full master catalog to one salon’s branches (bookable offerings).
     *
     * @param  iterable<SaloonBranch>  $branches
     * @param  array{
     *     services: array<string, Service>,
     *     products: array<string, Product>
     * }  $masters
     */
    public function attachMastersToSalon(Saloon $salon, iterable $branches, array $masters): void
    {
        $services = $masters['services'];
        $products = $masters['products'];
        $generalItemId = $products[self::GENERAL_ITEM]->id ?? null;

        foreach ($branches as $branch) {
            foreach ($services as $serviceName => $service) {
                if ($serviceName === PosCatalog::RETAIL_SERVICE_NAME) {
                    $this->seedRetailOfferings($salon, $branch, $service, $products);

                    continue;
                }

                $links = ServiceProduct::query()
                    ->where('service_id', $service->id)
                    ->get();

                SalonServiceProduct::query()->updateOrCreate(
                    [
                        'saloon_id' => $salon->id,
                        'branch_id' => $branch->id,
                        'service_id' => $service->id,
                        'product_id' => null,
                    ],
                    [
                        'price' => $service->default_price,
                        'duration_minutes' => $service->duration_minutes,
                        'is_active' => true,
                    ],
                );

                foreach ($links as $link) {
                    SalonServiceProduct::query()->updateOrCreate(
                        [
                            'saloon_id' => $salon->id,
                            'branch_id' => $branch->id,
                            'service_id' => $service->id,
                            'product_id' => $link->product_id,
                        ],
                        [
                            'price' => $link->default_price,
                            'duration_minutes' => $service->duration_minutes,
                            'is_active' => true,
                        ],
                    );
                }

                if ($links->isEmpty() && $generalItemId !== null) {
                    SalonServiceProduct::query()->updateOrCreate(
                        [
                            'saloon_id' => $salon->id,
                            'branch_id' => $branch->id,
                            'service_id' => $service->id,
                            'product_id' => $generalItemId,
                        ],
                        [
                            'price' => $service->default_price,
                            'duration_minutes' => $service->duration_minutes,
                            'is_active' => true,
                        ],
                    );
                }
            }
        }
    }

    /**
     * @return array<string, Category>
     */
    private function seedCategories(): array
    {
        $names = [
            'Hair',
            'Skin',
            'Nails',
            'Grooming',
            'Spa',
            'Makeup',
            'Bridal',
            'Retail',
        ];

        $categories = [];
        foreach ($names as $name) {
            $categories[$name] = Category::query()->updateOrCreate(
                ['name' => $name],
                ['is_active' => true],
            );
        }

        return $categories;
    }

    /**
     * @param  array<string, Category>  $categories
     * @return array<string, Product>
     */
    private function seedProducts(array $categories): array
    {
        // description is documentation-only (products table has no description column).
        $definitions = [
            self::GENERAL_ITEM => [
                'category' => 'Hair',
                'sku' => 'GEN-ITEM-001',
                'brand' => 'SalonOS',
                'unit' => 'unit',
                'description' => 'Default catch-all for services that do not require a branded product.',
            ],
            // Keep app/demo compatibility with DEFAULT_PRODUCT_NAME / DemoDataSeeder.
            'General Supplies' => [
                'category' => 'Hair',
                'sku' => 'GEN-001',
                'brand' => 'SalonOS',
                'unit' => 'pack',
                'description' => 'Legacy general consumables pack (alias of General Item for demos).',
            ],

            // Hair
            'Professional Shampoo' => [
                'category' => 'Hair',
                'sku' => 'HAIR-SHP-001',
                'brand' => 'Loreal Professionnel',
                'unit' => 'bottle',
                'description' => 'Salon-grade clarifying shampoo.',
            ],
            'Keratin Conditioner' => [
                'category' => 'Hair',
                'sku' => 'HAIR-CND-001',
                'brand' => 'Kerastase',
                'unit' => 'bottle',
                'description' => 'Smoothing keratin conditioner.',
            ],
            'Hair Color Kit — Natural Black' => [
                'category' => 'Hair',
                'sku' => 'HAIR-CLR-001',
                'brand' => 'Wella Professionals',
                'unit' => 'kit',
                'description' => 'Permanent color kit, natural black.',
            ],
            'Hair Color Kit — Brown' => [
                'category' => 'Hair',
                'sku' => 'HAIR-CLR-002',
                'brand' => 'Wella Professionals',
                'unit' => 'kit',
                'description' => 'Permanent color kit, medium brown.',
            ],
            'Hair Color Kit — Blonde Highlights' => [
                'category' => 'Hair',
                'sku' => 'HAIR-CLR-003',
                'brand' => 'Loreal Professionnel',
                'unit' => 'kit',
                'description' => 'Highlighting bleach + toner kit.',
            ],
            'Hair Spa Mask' => [
                'category' => 'Hair',
                'sku' => 'HAIR-SPA-001',
                'brand' => 'Schwarzkopf',
                'unit' => 'jar',
                'description' => 'Deep-conditioning spa mask.',
            ],
            'Hair Serum' => [
                'category' => 'Hair',
                'sku' => 'HAIR-SRM-001',
                'brand' => 'Matrix',
                'unit' => 'bottle',
                'description' => 'Anti-frizz finishing serum.',
            ],
            'Hair Straightening Cream' => [
                'category' => 'Hair',
                'sku' => 'HAIR-STR-001',
                'brand' => 'Schwarzkopf',
                'unit' => 'tube',
                'description' => 'Chemical straightening cream.',
            ],

            // Skin
            'Skincare Serum' => [
                'category' => 'Skin',
                'sku' => 'SKN-SRM-001',
                'brand' => 'The Ordinary',
                'unit' => 'bottle',
                'description' => 'Hydrating facial serum.',
            ],
            'Vitamin C Face Pack' => [
                'category' => 'Skin',
                'sku' => 'SKN-PCK-001',
                'brand' => 'Forest Essentials',
                'unit' => 'pack',
                'description' => 'Brightening vitamin C pack.',
            ],
            'Gold Facial Kit' => [
                'category' => 'Skin',
                'sku' => 'SKN-GLD-001',
                'brand' => 'O3+',
                'unit' => 'kit',
                'description' => '24K gold facial treatment kit.',
            ],
            'Acne Control Gel' => [
                'category' => 'Skin',
                'sku' => 'SKN-ACN-001',
                'brand' => 'Clinique',
                'unit' => 'tube',
                'description' => 'Oil-control acne treatment gel.',
            ],
            'Cleanup Scrub' => [
                'category' => 'Skin',
                'sku' => 'SKN-CLN-001',
                'brand' => 'Aroma Magic',
                'unit' => 'jar',
                'description' => 'Gentle facial cleanup scrub.',
            ],

            // Nails
            'Nail Polish Set' => [
                'category' => 'Nails',
                'sku' => 'NAIL-POL-001',
                'brand' => 'OPI',
                'unit' => 'set',
                'description' => 'Classic lacquer polish set.',
            ],
            'Gel Polish Kit' => [
                'category' => 'Nails',
                'sku' => 'NAIL-GEL-001',
                'brand' => 'CND Shellac',
                'unit' => 'kit',
                'description' => 'LED-cured gel polish kit.',
            ],
            'Nail Art Deco Pack' => [
                'category' => 'Nails',
                'sku' => 'NAIL-ART-001',
                'brand' => 'Born Pretty',
                'unit' => 'pack',
                'description' => 'Rhinestones and nail art foils.',
            ],
            'Cuticle Oil' => [
                'category' => 'Nails',
                'sku' => 'NAIL-CUT-001',
                'brand' => 'Sally Hansen',
                'unit' => 'bottle',
                'description' => 'Nourishing cuticle oil.',
            ],

            // Grooming
            'Beard Oil' => [
                'category' => 'Grooming',
                'sku' => 'GRM-BRD-001',
                'brand' => 'Beardo',
                'unit' => 'bottle',
                'description' => 'Conditioning beard oil.',
            ],
            'Shaving Cream' => [
                'category' => 'Grooming',
                'sku' => 'GRM-SHV-001',
                'brand' => 'The Man Company',
                'unit' => 'tube',
                'description' => 'Rich lather shaving cream.',
            ],
            'Aftershave Lotion' => [
                'category' => 'Grooming',
                'sku' => 'GRM-AFT-001',
                'brand' => 'Old Spice',
                'unit' => 'bottle',
                'description' => 'Cooling aftershave lotion.',
            ],
            'Hair Styling Wax' => [
                'category' => 'Grooming',
                'sku' => 'GRM-WAX-001',
                'brand' => 'Gatsby',
                'unit' => 'jar',
                'description' => 'Matte finish styling wax.',
            ],

            // Spa
            'Aromatherapy Oil' => [
                'category' => 'Spa',
                'sku' => 'SPA-ARO-001',
                'brand' => 'Soulflower',
                'unit' => 'bottle',
                'description' => 'Lavender aromatherapy massage oil.',
            ],
            'Body Scrub' => [
                'category' => 'Spa',
                'sku' => 'SPA-SCR-001',
                'brand' => 'The Body Shop',
                'unit' => 'jar',
                'description' => 'Exfoliating sugar body scrub.',
            ],
            'Massage Cream' => [
                'category' => 'Spa',
                'sku' => 'SPA-MSG-001',
                'brand' => 'Vlcc',
                'unit' => 'jar',
                'description' => 'Deep-tissue massage cream.',
            ],

            // Makeup
            'Foundation Kit' => [
                'category' => 'Makeup',
                'sku' => 'MUP-FND-001',
                'brand' => 'MAC',
                'unit' => 'kit',
                'description' => 'Professional foundation kit.',
            ],
            'Bridal Makeup Kit' => [
                'category' => 'Makeup',
                'sku' => 'MUP-BRD-001',
                'brand' => 'Huda Beauty',
                'unit' => 'kit',
                'description' => 'Full bridal makeup product set.',
            ],
            'Eye Makeup Palette' => [
                'category' => 'Makeup',
                'sku' => 'MUP-EYE-001',
                'brand' => 'Nykaa',
                'unit' => 'palette',
                'description' => 'Neutral + glam eyeshadow palette.',
            ],
        ];

        $products = [];
        foreach ($definitions as $name => $meta) {
            $payload = [
                'category_id' => $categories[$meta['category']]->id,
                'is_active' => true,
            ];

            if (Schema::hasColumn('products', 'sku')) {
                $payload['sku'] = $meta['sku'];
                $payload['brand'] = $meta['brand'];
                $payload['unit'] = $meta['unit'];
            }

            $products[$name] = Product::query()->updateOrCreate(
                ['name' => $name],
                $payload,
            );
        }

        return $products;
    }

    /**
     * Seed services and service↔product links.
     *
     * Each service definition:
     * - price / duration / gender / category
     * - products: list of product names with optional price overrides
     *   (omit products or use [GENERAL_ITEM] for a single default price)
     *
     * @param  array<string, Category>  $categories
     * @param  array<string, Product>  $products
     * @return array<string, Service>
     */
    private function seedServices(array $categories, array $products): array
    {
        $definitions = [
            // ── Hair ──────────────────────────────────────────────────────
            'Haircut & Styling' => [
                'category' => 'Hair',
                'price' => 499,
                'duration' => 45,
                'gender' => 'Unisex',
                'description' => 'Wash, cut, and basic blow-dry styling.',
                'products' => [self::GENERAL_ITEM],
            ],
            'Kids Haircut' => [
                'category' => 'Hair',
                'price' => 299,
                'duration' => 30,
                'gender' => 'Unisex',
                'description' => 'Haircut for children under 12.',
                'products' => [self::GENERAL_ITEM],
            ],
            'Hair Wash & Blow Dry' => [
                'category' => 'Hair',
                'price' => 399,
                'duration' => 35,
                'gender' => 'Unisex',
                'description' => 'Shampoo, condition, and blow-dry finish.',
                'products' => [
                    ['name' => 'Professional Shampoo', 'price' => 399],
                    ['name' => 'Keratin Conditioner', 'price' => 449],
                ],
            ],
            'Hair Color' => [
                'category' => 'Hair',
                'price' => 2499,
                'duration' => 120,
                'gender' => 'Unisex',
                'description' => 'Full-head permanent color with aftercare.',
                'products' => [
                    ['name' => 'Hair Color Kit — Natural Black', 'price' => 2499],
                    ['name' => 'Hair Color Kit — Brown', 'price' => 2699],
                    ['name' => 'Hair Color Kit — Blonde Highlights', 'price' => 3499],
                ],
            ],
            'Root Touch-Up' => [
                'category' => 'Hair',
                'price' => 999,
                'duration' => 60,
                'gender' => 'Unisex',
                'description' => 'Root-only color refresh.',
                'products' => [
                    ['name' => 'Hair Color Kit — Natural Black', 'price' => 999],
                    ['name' => 'Hair Color Kit — Brown', 'price' => 1099],
                ],
            ],
            'Hair Spa' => [
                'category' => 'Hair',
                'price' => 1299,
                'duration' => 75,
                'gender' => 'Unisex',
                'description' => 'Deep conditioning spa with steam and massage.',
                'products' => [
                    ['name' => 'Hair Spa Mask', 'price' => 1299],
                    ['name' => 'Hair Serum', 'price' => 1499],
                ],
            ],
            'Hair Straightening' => [
                'category' => 'Hair',
                'price' => 4999,
                'duration' => 180,
                'gender' => 'Unisex',
                'description' => 'Chemical straightening / smoothening treatment.',
                'products' => [
                    ['name' => 'Hair Straightening Cream', 'price' => 4999],
                ],
            ],
            'Keratin Treatment' => [
                'category' => 'Hair',
                'price' => 5999,
                'duration' => 150,
                'gender' => 'Unisex',
                'description' => 'Keratin smoothening for frizz control.',
                'products' => [
                    ['name' => 'Keratin Conditioner', 'price' => 5999],
                    ['name' => 'Hair Serum', 'price' => 6499],
                ],
            ],

            // ── Skin ──────────────────────────────────────────────────────
            'Classic Facial' => [
                'category' => 'Skin',
                'price' => 899,
                'duration' => 60,
                'gender' => 'Unisex',
                'description' => 'Cleansing, scrub, massage, mask, and moisturizer.',
                'products' => [
                    ['name' => 'Skincare Serum', 'price' => 899],
                    ['name' => 'Cleanup Scrub', 'price' => 799],
                ],
            ],
            'Gold Facial' => [
                'category' => 'Skin',
                'price' => 1999,
                'duration' => 75,
                'gender' => 'Unisex',
                'description' => 'Premium 24K gold facial for radiance.',
                'products' => [
                    ['name' => 'Gold Facial Kit', 'price' => 1999],
                ],
            ],
            'Fruit Facial' => [
                'category' => 'Skin',
                'price' => 999,
                'duration' => 60,
                'gender' => 'Unisex',
                'description' => 'Vitamin-rich fruit extract facial.',
                'products' => [
                    ['name' => 'Vitamin C Face Pack', 'price' => 999],
                ],
            ],
            'Acne Facial' => [
                'category' => 'Skin',
                'price' => 1199,
                'duration' => 70,
                'gender' => 'Unisex',
                'description' => 'Deep-clean facial for acne-prone skin.',
                'products' => [
                    ['name' => 'Acne Control Gel', 'price' => 1199],
                    ['name' => 'Cleanup Scrub', 'price' => 1099],
                ],
            ],
            'Face Cleanup' => [
                'category' => 'Skin',
                'price' => 499,
                'duration' => 30,
                'gender' => 'Unisex',
                'description' => 'Quick cleanup with scrub and toner.',
                'products' => [
                    ['name' => 'Cleanup Scrub', 'price' => 499],
                ],
            ],
            'Threading — Eyebrows' => [
                'category' => 'Skin',
                'price' => 79,
                'duration' => 15,
                'gender' => 'Female',
                'description' => 'Eyebrow shaping with thread.',
                'products' => [self::GENERAL_ITEM],
            ],
            'Threading — Full Face' => [
                'category' => 'Skin',
                'price' => 249,
                'duration' => 30,
                'gender' => 'Female',
                'description' => 'Full-face threading including upper lip and chin.',
                'products' => [self::GENERAL_ITEM],
            ],

            // ── Nails ─────────────────────────────────────────────────────
            'Manicure' => [
                'category' => 'Nails',
                'price' => 499,
                'duration' => 40,
                'gender' => 'Unisex',
                'description' => 'Nail shaping, cuticle care, polish, and hand massage.',
                'products' => [
                    ['name' => 'Nail Polish Set', 'price' => 499],
                    ['name' => 'Cuticle Oil', 'price' => 549],
                ],
            ],
            'Pedicure' => [
                'category' => 'Nails',
                'price' => 699,
                'duration' => 50,
                'gender' => 'Unisex',
                'description' => 'Foot soak, scrub, nail care, and polish.',
                'products' => [
                    ['name' => 'Nail Polish Set', 'price' => 699],
                    ['name' => 'Cuticle Oil', 'price' => 749],
                ],
            ],
            'Gel Manicure' => [
                'category' => 'Nails',
                'price' => 999,
                'duration' => 55,
                'gender' => 'Unisex',
                'description' => 'Long-wear LED gel manicure.',
                'products' => [
                    ['name' => 'Gel Polish Kit', 'price' => 999],
                ],
            ],
            'Nail Art' => [
                'category' => 'Nails',
                'price' => 399,
                'duration' => 30,
                'gender' => 'Unisex',
                'description' => 'Custom nail art on polished nails (add-on).',
                'products' => [
                    ['name' => 'Nail Art Deco Pack', 'price' => 399],
                    ['name' => 'Gel Polish Kit', 'price' => 549],
                ],
            ],

            // ── Grooming ──────────────────────────────────────────────────
            'Beard Trim' => [
                'category' => 'Grooming',
                'price' => 199,
                'duration' => 20,
                'gender' => 'Male',
                'description' => 'Beard shape and trim.',
                'products' => [self::GENERAL_ITEM],
            ],
            'Beard Grooming' => [
                'category' => 'Grooming',
                'price' => 349,
                'duration' => 30,
                'gender' => 'Male',
                'description' => 'Trim, wash, oil, and style.',
                'products' => [
                    ['name' => 'Beard Oil', 'price' => 349],
                    ['name' => 'Hair Styling Wax', 'price' => 399],
                ],
            ],
            'Clean Shave' => [
                'category' => 'Grooming',
                'price' => 249,
                'duration' => 25,
                'gender' => 'Male',
                'description' => 'Hot towel shave with aftershave.',
                'products' => [
                    ['name' => 'Shaving Cream', 'price' => 249],
                    ['name' => 'Aftershave Lotion', 'price' => 279],
                ],
            ],
            'Haircut + Beard Combo' => [
                'category' => 'Grooming',
                'price' => 649,
                'duration' => 55,
                'gender' => 'Male',
                'description' => 'Haircut with beard trim and style.',
                'products' => [
                    ['name' => 'Beard Oil', 'price' => 649],
                    ['name' => 'Hair Styling Wax', 'price' => 699],
                ],
            ],

            // ── Spa ───────────────────────────────────────────────────────
            'Head Massage' => [
                'category' => 'Spa',
                'price' => 499,
                'duration' => 30,
                'gender' => 'Unisex',
                'description' => 'Relaxing scalp and shoulder massage.',
                'products' => [
                    ['name' => 'Aromatherapy Oil', 'price' => 499],
                    ['name' => self::GENERAL_ITEM, 'price' => 499],
                ],
            ],
            'Body Massage — 60 min' => [
                'category' => 'Spa',
                'price' => 1499,
                'duration' => 60,
                'gender' => 'Unisex',
                'description' => 'Full-body Swedish-style massage.',
                'products' => [
                    ['name' => 'Massage Cream', 'price' => 1499],
                    ['name' => 'Aromatherapy Oil', 'price' => 1599],
                ],
            ],
            'Body Scrub' => [
                'category' => 'Spa',
                'price' => 1199,
                'duration' => 45,
                'gender' => 'Unisex',
                'description' => 'Exfoliating full-body scrub and moisturize.',
                'products' => [
                    ['name' => 'Body Scrub', 'price' => 1199],
                ],
            ],
            'Foot Spa' => [
                'category' => 'Spa',
                'price' => 599,
                'duration' => 40,
                'gender' => 'Unisex',
                'description' => 'Foot soak, scrub, and reflexology massage.',
                'products' => [
                    ['name' => 'Aromatherapy Oil', 'price' => 599],
                    ['name' => self::GENERAL_ITEM, 'price' => 599],
                ],
            ],

            // ── Makeup ────────────────────────────────────────────────────
            'Party Makeup' => [
                'category' => 'Makeup',
                'price' => 1999,
                'duration' => 60,
                'gender' => 'Female',
                'description' => 'Event / party makeup look.',
                'products' => [
                    ['name' => 'Foundation Kit', 'price' => 1999],
                    ['name' => 'Eye Makeup Palette', 'price' => 2299],
                ],
            ],
            'Engagement Makeup' => [
                'category' => 'Makeup',
                'price' => 4999,
                'duration' => 90,
                'gender' => 'Female',
                'description' => 'Engagement / reception glam makeup.',
                'products' => [
                    ['name' => 'Bridal Makeup Kit', 'price' => 4999],
                    ['name' => 'Foundation Kit', 'price' => 4499],
                ],
            ],

            // ── Bridal ────────────────────────────────────────────────────
            'Bridal Makeup Package' => [
                'category' => 'Bridal',
                'price' => 14999,
                'duration' => 180,
                'gender' => 'Female',
                'description' => 'Full bridal makeup with trial touch-up kit.',
                'products' => [
                    ['name' => 'Bridal Makeup Kit', 'price' => 14999],
                ],
            ],
            'Bridal Hair Styling' => [
                'category' => 'Bridal',
                'price' => 3999,
                'duration' => 90,
                'gender' => 'Female',
                'description' => 'Bridal updo / open hairstyle with accessories.',
                'products' => [
                    ['name' => 'Hair Serum', 'price' => 3999],
                    ['name' => self::GENERAL_ITEM, 'price' => 3999],
                ],
            ],
            'Pre-Bridal Skin Package' => [
                'category' => 'Bridal',
                'price' => 7999,
                'duration' => 120,
                'gender' => 'Female',
                'description' => 'Multi-step pre-bridal facial and cleanup package.',
                'products' => [
                    ['name' => 'Gold Facial Kit', 'price' => 7999],
                    ['name' => 'Vitamin C Face Pack', 'price' => 7499],
                ],
            ],
        ];

        $services = [];

        foreach ($definitions as $name => $meta) {
            $service = Service::query()->updateOrCreate(
                ['name' => $name],
                [
                    'category_id' => $categories[$meta['category']]->id,
                    'default_price' => $meta['price'],
                    'duration_minutes' => $meta['duration'],
                    'gender' => $meta['gender'],
                    'is_active' => true,
                ],
            );

            $services[$name] = $service;

            $productLinks = $this->normalizeProductLinks($meta['products'] ?? [self::GENERAL_ITEM], (float) $meta['price']);

            foreach ($productLinks as $link) {
                $productName = $link['name'];
                if (! isset($products[$productName])) {
                    continue;
                }

                ServiceProduct::query()->updateOrCreate(
                    [
                        'service_id' => $service->id,
                        'product_id' => $products[$productName]->id,
                    ],
                    ['default_price' => $link['price']],
                );
            }
        }

        return $services;
    }

    /**
     * @param  array<int, string|array{name: string, price?: float|int}>  $products
     * @return array<int, array{name: string, price: float}>
     */
    private function normalizeProductLinks(array $products, float $fallbackPrice): array
    {
        $links = [];

        foreach ($products as $entry) {
            if (is_string($entry)) {
                $links[] = ['name' => $entry, 'price' => $fallbackPrice];

                continue;
            }

            $links[] = [
                'name' => $entry['name'],
                'price' => (float) ($entry['price'] ?? $fallbackPrice),
            ];
        }

        // Guarantee at least one General Item link when list was empty.
        if ($links === []) {
            $links[] = ['name' => self::GENERAL_ITEM, 'price' => $fallbackPrice];
        }

        return $links;
    }

    /**
     * @param  array<string, Category>  $categories
     * @param  array<string, Service>  $services
     */
    private function seedRetailService(array $categories, array &$services): void
    {
        $services[PosCatalog::RETAIL_SERVICE_NAME] = Service::query()->updateOrCreate(
            ['name' => PosCatalog::RETAIL_SERVICE_NAME],
            [
                'category_id' => $categories['Retail']->id,
                'default_price' => 0,
                'duration_minutes' => PosCatalog::RETAIL_DURATION_MINUTES,
                'gender' => 'Unisex',
                'is_active' => true,
            ],
        );
    }

    /**
     * Attach every seeded service offering to all active salon branches so
     * the catalog is immediately bookable in local/dev environments.
     *
     * @param  array<string, Service>  $services
     * @param  array<string, Product>  $products
     */
    private function attachToExistingSalons(array $services, array $products): void
    {
        $salons = Saloon::query()->where('is_active', true)->get();
        if ($salons->isEmpty()) {
            return;
        }

        foreach ($salons as $salon) {
            $branches = SaloonBranch::query()
                ->where('saloon_id', $salon->id)
                ->where('is_active', true)
                ->get();

            if ($branches->isEmpty()) {
                continue;
            }

            $this->attachMastersToSalon($salon, $branches, [
                'services' => $services,
                'products' => $products,
            ]);
        }
    }

    /**
     * Retail POS products sold under the synthetic Retail Product service.
     *
     * @param  array<string, Product>  $products
     */
    private function seedRetailOfferings(
        Saloon $salon,
        SaloonBranch $branch,
        Service $retailService,
        array $products,
    ): void {
        $retailPrices = [
            'Professional Shampoo' => 650,
            'Keratin Conditioner' => 890,
            'Hair Serum' => 750,
            'Skincare Serum' => 1200,
            'Nail Polish Set' => 450,
            'Gel Polish Kit' => 980,
            'Beard Oil' => 499,
            'Shaving Cream' => 299,
            'Hair Styling Wax' => 349,
            'Aromatherapy Oil' => 599,
            'Body Scrub' => 799,
            'Cuticle Oil' => 280,
        ];

        foreach ($retailPrices as $productName => $price) {
            if (! isset($products[$productName])) {
                continue;
            }

            SalonServiceProduct::query()->updateOrCreate(
                [
                    'saloon_id' => $salon->id,
                    'branch_id' => $branch->id,
                    'service_id' => $retailService->id,
                    'product_id' => $products[$productName]->id,
                ],
                [
                    'price' => $price,
                    'duration_minutes' => PosCatalog::RETAIL_DURATION_MINUTES,
                    'is_active' => true,
                ],
            );
        }
    }
}
