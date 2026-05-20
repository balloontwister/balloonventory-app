<?php

namespace Database\Seeders;

use App\Models\PackagingType;
use Illuminate\Database\Seeder;

class PackagingTypeSeeder extends Seeder
{
    public function run(): void
    {
        // Skip once the table holds data — catalog data is curated by hand in production.
        if (PackagingType::withTrashed()->exists()) {
            return;
        }

        $types = [
            ['name' => 'Individual', 'sort_order' => 10],
            ['name' => 'Loose',      'sort_order' => 20],
            ['name' => 'Nozzle Up',  'sort_order' => 30],
            ['name' => 'Retail',     'sort_order' => 40],
        ];

        foreach ($types as $data) {
            PackagingType::firstOrCreate(['name' => $data['name']], $data);
        }
    }
}
