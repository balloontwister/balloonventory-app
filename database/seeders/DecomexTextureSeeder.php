<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Material;
use App\Models\Texture;
use App\Models\TextureFamily;
use Illuminate\Database\Seeder;

class DecomexTextureSeeder extends Seeder
{
    public function run(): void
    {
        $decomex = Brand::where('name', 'Decomex')->firstOrFail();
        $latex = Material::where('name', 'Latex')->firstOrFail();

        // Pastel and Dusk are required by Decomex textures; ensure they exist
        // so fresh installs and tests don't need an out-of-band setup step.
        $familyDefaults = [
            'Pastel' => 60,
            'Dusk' => 70,
        ];

        foreach ($familyDefaults as $name => $sortOrder) {
            TextureFamily::firstOrCreate(['name' => $name], ['sort_order' => $sortOrder]);
        }

        $families = TextureFamily::pluck('id', 'name');

        $textures = [
            ['name' => 'Standard (D)',      'family' => 'Standard', 'sort_order' => 610],
            ['name' => 'Pastel Deco (D)',   'family' => 'Pastel',   'sort_order' => 620],
            ['name' => 'Jewel Crystal (D)', 'family' => 'Crystal',  'sort_order' => 630],
            ['name' => 'Pearl/Metallic (D)', 'family' => 'Metallic', 'sort_order' => 640],
            ['name' => 'Luster (D)',        'family' => 'Chrome',   'sort_order' => 650],
        ];

        foreach ($textures as $data) {
            Texture::firstOrCreate(
                ['name' => $data['name'], 'material_id' => $latex->id, 'brand_id' => $decomex->id],
                [
                    'texture_family_id' => $families[$data['family']],
                    'sort_order' => $data['sort_order'],
                ],
            );
        }
    }
}
