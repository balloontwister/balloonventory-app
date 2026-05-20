<?php

namespace Database\Seeders;

use App\Models\PrintSide;
use Illuminate\Database\Seeder;

class PrintSideSeeder extends Seeder
{
    public function run(): void
    {
        // Skip once the table holds data — catalog data is curated by hand in production.
        if (PrintSide::withTrashed()->exists()) {
            return;
        }

        $sides = [
            ['name' => 'Top',        'sort_order' => 10],
            ['name' => 'Side',       'sort_order' => 20],
            ['name' => 'Two-Sides',  'sort_order' => 30],
            ['name' => 'Four-Sides', 'sort_order' => 40],
            ['name' => 'Five-Sides', 'sort_order' => 50],
        ];

        foreach ($sides as $data) {
            PrintSide::firstOrCreate(['name' => $data['name']], $data);
        }
    }
}
