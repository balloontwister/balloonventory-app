<?php

namespace Database\Seeders;

use App\Models\Material;
use Illuminate\Database\Seeder;

class MaterialSeeder extends Seeder
{
    public function run(): void
    {
        $materials = [
            ['name' => 'Latex',        'sort_order' => 10],
            ['name' => 'Foil',         'sort_order' => 20],
            ['name' => 'Plastic',      'sort_order' => 30],
            ['name' => 'Chloroprene',  'sort_order' => 40],
            ['name' => 'Stretchy',     'sort_order' => 50],
        ];

        foreach ($materials as $data) {
            Material::firstOrCreate(['name' => $data['name']], $data);
        }
    }
}
