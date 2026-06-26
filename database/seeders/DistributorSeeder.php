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
                'config' => ['collection_handle' => 'all', 'has_json_api' => true],
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
