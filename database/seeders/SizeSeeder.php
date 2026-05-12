<?php

namespace Database\Seeders;

use App\Models\Size;
use Illuminate\Database\Seeder;

/**
 * Canonical balloon sizes.
 *
 * Round latex sizes use `name` for the primary US label and
 * `alt_imperial_name` for the alternate label that the same physical balloon
 * ships under in other regions. `diameter_cm` is the metric value rendered
 * alongside the imperial label (e.g. "11" / 12" (30 cm)").
 *
 * 5" and 6" are consolidated under "5-inch" because manufacturers ship them
 * as a single product; 11" and 12" similarly consolidate under "11-inch".
 * Per Sempertex's R-12 product code, both are 30 cm — the imperial naming is
 * historical regional drift.
 *
 * Modeling sizes (160, 260, 350, 360, 646, 660) leave `diameter_cm` NULL
 * because the name itself is the spec (inflated diameter × length in inches).
 */
class SizeSeeder extends Seeder
{
    public function run(): void
    {
        $sizes = [
            // Round latex
            ['name' => '5-inch',  'alt_imperial_name' => '6-inch',  'diameter_cm' => 13,  'size_category' => 'small',          'sort_order' => 10],
            ['name' => '11-inch', 'alt_imperial_name' => '12-inch', 'diameter_cm' => 30,  'size_category' => 'medium',         'sort_order' => 30],
            ['name' => '16-inch', 'alt_imperial_name' => null,      'diameter_cm' => 40,  'size_category' => 'large',          'sort_order' => 50],
            ['name' => '24-inch', 'alt_imperial_name' => null,      'diameter_cm' => 60,  'size_category' => 'large',          'sort_order' => 60],
            ['name' => '36-inch', 'alt_imperial_name' => null,      'diameter_cm' => 90,  'size_category' => 'giant',          'sort_order' => 70],
            ['name' => '72-inch', 'alt_imperial_name' => null,      'diameter_cm' => 180, 'size_category' => 'giant',          'sort_order' => 80],
            // Modeling / twisting — name is the spec
            ['name' => '160',     'alt_imperial_name' => null,      'diameter_cm' => null, 'size_category' => 'small_modeling', 'sort_order' => 90],
            ['name' => '260',     'alt_imperial_name' => null,      'diameter_cm' => null, 'size_category' => 'small_modeling', 'sort_order' => 100],
            ['name' => '350',     'alt_imperial_name' => null,      'diameter_cm' => null, 'size_category' => 'large_modeling', 'sort_order' => 110],
            ['name' => '360',     'alt_imperial_name' => null,      'diameter_cm' => null, 'size_category' => 'large_modeling', 'sort_order' => 120],
            ['name' => '646',     'alt_imperial_name' => null,      'diameter_cm' => null, 'size_category' => 'large_modeling', 'sort_order' => 130],
            ['name' => '660',     'alt_imperial_name' => null,      'diameter_cm' => null, 'size_category' => 'large_modeling', 'sort_order' => 140],
        ];

        foreach ($sizes as $data) {
            Size::updateOrCreate(['name' => $data['name']], $data);
        }

        // Remove rows from the previous separated-imperial scheme. Safe because
        // no SKUs reference them in v1.
        Size::whereIn('name', ['6-inch', '12-inch'])->forceDelete();
    }
}
