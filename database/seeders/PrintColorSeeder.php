<?php

namespace Database\Seeders;

use App\Models\PrintColor;
use Illuminate\Database\Seeder;

class PrintColorSeeder extends Seeder
{
    public function run(): void
    {
        $colors = [
            ['name' => 'Black',   'sort_order' => 10],
            ['name' => 'White',   'sort_order' => 20],
            ['name' => 'Red',     'sort_order' => 30],
            ['name' => 'Blue',    'sort_order' => 40],
            ['name' => 'Green',   'sort_order' => 50],
            ['name' => 'Yellow',  'sort_order' => 60],
            ['name' => 'Gold',    'sort_order' => 70],
            ['name' => 'Silver',  'sort_order' => 80],
            ['name' => 'Pink',    'sort_order' => 90],
            ['name' => 'Purple',  'sort_order' => 100],
            ['name' => 'Orange',  'sort_order' => 110],
        ];

        foreach ($colors as $data) {
            PrintColor::firstOrCreate(['name' => $data['name']], $data);
        }
    }
}
