<?php

namespace Database\Seeders;

use App\Models\ColorFamily;
use Illuminate\Database\Seeder;

class ColorFamilySeeder extends Seeder
{
    public function run(): void
    {
        $families = [
            ['name' => 'Reds',     'sort_order' => 10],
            ['name' => 'Pinks',    'sort_order' => 20],
            ['name' => 'Oranges',  'sort_order' => 30],
            ['name' => 'Yellows',  'sort_order' => 40],
            ['name' => 'Greens',   'sort_order' => 50],
            ['name' => 'Blues',    'sort_order' => 60],
            ['name' => 'Purples',  'sort_order' => 70],
            ['name' => 'Browns',   'sort_order' => 80],
            ['name' => 'Whites',   'sort_order' => 90],
            ['name' => 'Blacks',   'sort_order' => 100],
            ['name' => 'Silvers',  'sort_order' => 110],
            ['name' => 'Golds',    'sort_order' => 120],
            ['name' => 'Clears',   'sort_order' => 130],
        ];

        foreach ($families as $data) {
            ColorFamily::firstOrCreate(['name' => $data['name']], $data);
        }
    }
}
