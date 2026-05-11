<?php

namespace Database\Seeders;

use App\Models\Shape;
use Illuminate\Database\Seeder;

class ShapeSeeder extends Seeder
{
    public function run(): void
    {
        $shapes = [
            ['name' => 'Round',       'sort_order' => 10],
            ['name' => 'Link',        'sort_order' => 20],
            ['name' => 'Non-round',   'sort_order' => 30],
            ['name' => 'Heart',       'sort_order' => 40],
            ['name' => 'Circle',      'sort_order' => 50],
            ['name' => 'Star',        'sort_order' => 60],
            ['name' => 'Shaped',      'sort_order' => 70],
            ['name' => 'SuperShape',  'sort_order' => 80],
            ['name' => 'Other',       'sort_order' => 90],
        ];

        foreach ($shapes as $data) {
            Shape::firstOrCreate(['name' => $data['name']], $data);
        }
    }
}
