<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        // Skip once the table holds data — catalog data is curated by hand in production.
        if (Brand::withTrashed()->exists()) {
            return;
        }

        $brands = [
            ['name' => 'Qualatex',    'abbreviation' => 'QTX', 'sort_order' => 10],
            ['name' => 'Sempertex',   'abbreviation' => 'STX', 'sort_order' => 20],
            ['name' => 'Betallic',    'abbreviation' => 'BET', 'sort_order' => 30],
            ['name' => 'Kalisan',     'abbreviation' => 'KAL', 'sort_order' => 40],
            ['name' => 'TufTex',      'abbreviation' => 'TTX', 'sort_order' => 50],
            ['name' => 'Decomex',     'abbreviation' => 'DCX', 'sort_order' => 60],
            ['name' => 'Funsational', 'abbreviation' => 'FSN', 'sort_order' => 70],
        ];

        foreach ($brands as $data) {
            Brand::firstOrCreate(['name' => $data['name']], $data);
        }
    }
}
