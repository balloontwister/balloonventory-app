<?php

namespace Database\Seeders;

use App\Models\TextureFamily;
use Illuminate\Database\Seeder;

class TextureFamilySeeder extends Seeder
{
    public function run(): void
    {
        // Skip once the table holds data — catalog data is curated by hand in production.
        if (TextureFamily::withTrashed()->exists()) {
            return;
        }

        $families = [
            ['name' => 'Standard',  'sort_order' => 10],
            ['name' => 'Crystal',   'sort_order' => 20],
            ['name' => 'Metallic',  'sort_order' => 30],
            ['name' => 'Neon',      'sort_order' => 40],
            ['name' => 'Chrome',    'sort_order' => 50],
        ];

        foreach ($families as $data) {
            TextureFamily::firstOrCreate(['name' => $data['name']], $data);
        }
    }
}
