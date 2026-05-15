<?php

namespace Database\Seeders;

use App\Models\ColorFamily;
use App\Models\Material;
use Illuminate\Database\Seeder;

class ColorFamilySeeder extends Seeder
{
    public function run(): void
    {
        $latex = Material::where('name', 'Latex')->first();

        $families = [
            ['name' => 'Reds',     'sort_order' => 10,  'color_hex' => '#DC2626'],
            ['name' => 'Pinks',    'sort_order' => 20,  'color_hex' => '#EC4899'],
            ['name' => 'Oranges',  'sort_order' => 30,  'color_hex' => '#F97316'],
            ['name' => 'Yellows',  'sort_order' => 40,  'color_hex' => '#EAB308'],
            ['name' => 'Greens',   'sort_order' => 50,  'color_hex' => '#16A34A'],
            ['name' => 'Blues',    'sort_order' => 60,  'color_hex' => '#2563EB'],
            ['name' => 'Purples',  'sort_order' => 70,  'color_hex' => '#7C3AED'],
            ['name' => 'Browns',   'sort_order' => 80,  'color_hex' => '#92400E'],
            ['name' => 'Whites',   'sort_order' => 90,  'color_hex' => '#F8FAFC'],
            ['name' => 'Blacks',   'sort_order' => 100, 'color_hex' => '#0A0A0A'],
            ['name' => 'Silvers',  'sort_order' => 110, 'color_hex' => '#9CA3AF'],
            ['name' => 'Golds',    'sort_order' => 120, 'color_hex' => '#D4A017'],
            ['name' => 'Clears',   'sort_order' => 130, 'color_hex' => '#E5E7EB'],
        ];

        foreach ($families as $data) {
            $data['material_id'] = $latex?->id;
            ColorFamily::updateOrCreate(['name' => $data['name']], $data);
        }
    }
}
