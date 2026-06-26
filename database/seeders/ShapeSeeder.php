<?php

namespace Database\Seeders;

use App\Models\Material;
use App\Models\Shape;
use Illuminate\Database\Seeder;

class ShapeSeeder extends Seeder
{
    public function run(): void
    {
        // Skip once the table holds data — catalog data is curated by hand in production.
        if (Shape::withTrashed()->exists()) {
            return;
        }

        $latex = Material::where('name', 'Latex')->firstOrFail();
        $foil = Material::where('name', 'Foil')->firstOrFail();

        $shapes = [
            // Latex shapes
            ['name' => 'Round',          'material' => $latex, 'sort_order' => 10,  'description' => null],
            ['name' => 'Link',           'material' => $latex, 'sort_order' => 20,  'description' => null],
            ['name' => 'Non-round',      'material' => $latex, 'sort_order' => 30,  'description' => null],
            ['name' => 'Heart',          'material' => $latex, 'sort_order' => 40,  'description' => null],
            ['name' => '321-Bee Body',   'material' => $latex, 'sort_order' => 70,  'description' => 'A tapered balloon, made by Qualatex'],
            ['name' => 'Geo',            'material' => $latex, 'sort_order' => 80,  'description' => 'A round or blossom-shaped balloon with a hole in the middle. Made by Qualatex'],
            ['name' => 'Multi-shape',    'material' => $latex, 'sort_order' => 85,  'description' => null],
            ['name' => 'Other',          'material' => $latex, 'sort_order' => 90,  'description' => null],
            // Foil shapes
            ['name' => 'Round Foil',     'material' => $foil,  'sort_order' => 100, 'description' => null],
            ['name' => 'Square Foil',    'material' => $foil,  'sort_order' => 115, 'description' => null],
            ['name' => 'Circle Foil',    'material' => $foil,  'sort_order' => 150, 'description' => null],
            ['name' => 'Star Foil',      'material' => $foil,  'sort_order' => 160, 'description' => null],
            ['name' => 'Shaped',         'material' => $foil,  'sort_order' => 170, 'description' => null],
            ['name' => 'SuperShape (foil)', 'material' => $foil, 'sort_order' => 180, 'description' => null],
        ];

        foreach ($shapes as $data) {
            Shape::firstOrCreate(
                ['name' => $data['name'], 'material_id' => $data['material']->id],
                [
                    'material_id' => $data['material']->id,
                    'sort_order' => $data['sort_order'],
                    'description' => $data['description'],
                ],
            );
        }
    }
}
