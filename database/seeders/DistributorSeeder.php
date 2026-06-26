<?php

namespace Database\Seeders;

use App\Models\Distributor;
use Illuminate\Database\Seeder;

class DistributorSeeder extends Seeder
{
    public function run(): void
    {
        $distributors = [
            [
                'name' => 'BargainBalloons',
                'slug' => 'bargain-balloons',
                'platform_type' => 'shopify',
                'base_url' => 'https://bargainballoons.com',
                // Shopify: bulk products.json gives barcode/vendor/price; the rich
                // attributes live in the page's "Additional Product Details" accordion
                // (a <ul><li><span> spec list), read via the attribute_list recipe.
                // Brand comes from the JSON vendor; shape is absent (default Round).
                'config' => [
                    'collection_handle' => 'all',
                    'has_json_api' => true,
                    'extraction' => [
                        'attribute_list' => ['section_marker' => 'Additional Product Details'],
                        'required_labels' => ['Manufacturer Color', 'Latex Finish', 'Package Count'],
                        'min_rows' => 5,
                        // The store's labels → our canonical attribute keys.
                        'label_map' => [
                            'size' => 'Size (inches)',
                            'color' => 'Manufacturer Color',
                            'texture' => 'Latex Finish',
                            'count' => 'Package Count',
                            'packaging' => 'Packaging Type',
                        ],
                    ],
                    'attribute_aliases' => [
                        'brand' => ['Betallatex' => 'Sempertex'], // Betallic's old latex rebrand (title path)
                        'packaging' => ['Retail Packaged' => 'Retail'],
                    ],
                    // Sempertex markets its code-12 / 30 cm rounds as "11 inch".
                    'size_number_aliases' => ['Sempertex' => ['11' => '12']],
                    // Betallic remnants on the SKU → bare manufacturer item number.
                    'sku_strip_prefixes' => ['BL-'],
                    'sku_strip_suffixes' => ['-B'],
                ],
                'is_active' => true,
                'sort_order' => 0,
            ],
            [
                'name' => 'Larocks',
                'slug' => 'larocks',
                'platform_type' => 'bigcommerce',
                'base_url' => 'https://larocks.com',
                // Extraction recipe: Larocks renders an "Extra Information" table
                // of label/value divs we read for the product's real attributes.
                // required_labels + min_rows let us trust a page — and detect when
                // the template changes (a drop in matched rows trips it).
                'config' => [
                    'extraction' => [
                        'attribute_table' => [
                            'header_class' => 'productView-table-header',
                            'value_class' => 'productView-table-data',
                        ],
                        'required_labels' => ['Brand', 'Industry'],
                        'min_rows' => 4,
                    ],
                    // Distributor vocabulary → our reference rows. Packaging values
                    // here are Larocks' "Package Type" wording.
                    'attribute_aliases' => [
                        'packaging' => [
                            'Nozzle-Up' => 'Nozzle Up',          // from "Q-Pak / Nozzle-Up" (slash-split)
                            'Loose Bag (Regular)' => 'Loose',
                            'Packaged' => 'Retail',
                        ],
                    ],
                    // Per-brand size-number quirks: Sempertex sells its code-12 /
                    // 30 cm balloons as "11 inch", so 11 → 12 (→ R-12/C-12/LOL-12).
                    'size_number_aliases' => [
                        'Sempertex' => ['11' => '12'],
                    ],
                ],
                'is_active' => true,
                'sort_order' => 1,
            ],
        ];

        foreach ($distributors as $data) {
            Distributor::firstOrCreate(
                ['slug' => $data['slug']],
                $data,
            );
        }
    }
}
