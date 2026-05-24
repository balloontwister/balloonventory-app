<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Material;
use App\Models\Texture;
use App\Models\TextureFamily;
use Illuminate\Database\Seeder;

class SempertexTextureSeeder extends Seeder
{
    public function run(): void
    {
        $sempertex = Brand::where('name', 'Sempertex')->firstOrFail();
        $latex = Material::where('name', 'Latex')->firstOrFail();

        // Standard, Crystal, Metallic, Neon, and Chrome ship with
        // TextureFamilySeeder; Pastel/Dusk/Silk are added on demand here so
        // fresh installs and tests don't need an out-of-band setup step.
        $familyDefaults = [
            'Standard' => 10,
            'Crystal' => 20,
            'Metallic' => 30,
            'Neon' => 40,
            'Chrome' => 50,
            'Pastel' => 60,
            'Dusk' => 70,
            'Silk' => 80,
        ];

        foreach ($familyDefaults as $name => $sortOrder) {
            TextureFamily::firstOrCreate(['name' => $name], ['sort_order' => $sortOrder]);
        }

        $families = TextureFamily::pluck('id', 'name');

        $textures = [
            ['name' => 'Fashion (S)',      'family' => 'Standard', 'sort_order' => 300],
            ['name' => 'Deluxe (S)',       'family' => 'Standard', 'sort_order' => 310],
            ['name' => 'Crystal (S)',      'family' => 'Crystal',  'sort_order' => 320],
            ['name' => 'Neon (S)',         'family' => 'Neon',     'sort_order' => 330],
            ['name' => 'Pastel Matte (S)', 'family' => 'Pastel',   'sort_order' => 340],
            ['name' => 'Pastel Dusk (S)',  'family' => 'Dusk',     'sort_order' => 350],
            ['name' => 'Pearl (S)',        'family' => 'Metallic', 'sort_order' => 360],
            ['name' => 'Reflex (S)',       'family' => 'Chrome',   'sort_order' => 370],
            ['name' => 'Silk (S)',         'family' => 'Silk',     'sort_order' => 380],
            ['name' => 'Satin (S)',        'family' => 'Silk',     'sort_order' => 385],
            ['name' => 'Metallic (S)',     'family' => 'Metallic', 'sort_order' => 390],
        ];

        foreach ($textures as $data) {
            Texture::firstOrCreate(
                ['name' => $data['name'], 'material_id' => $latex->id, 'brand_id' => $sempertex->id],
                [
                    'texture_family_id' => $families[$data['family']],
                    'sort_order' => $data['sort_order'],
                ],
            );
        }
    }
}
