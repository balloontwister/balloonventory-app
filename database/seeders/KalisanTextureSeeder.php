<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Material;
use App\Models\Texture;
use App\Models\TextureFamily;
use Illuminate\Database\Seeder;

class KalisanTextureSeeder extends Seeder
{
    public function run(): void
    {
        $kalisan = Brand::where('name', 'Kalisan')->firstOrFail();
        $latex = Material::where('name', 'Latex')->firstOrFail();

        /**
         * Texture families used by the Kalisan line. Standard, Crystal,
         * Metallic, and Chrome are already in TextureFamilySeeder; Pastel and
         * Silk are Kalisan-specific (Macaron rolls into Pastel; Opaque Satin
         * rolls into Silk). firstOrCreate keeps this no-op on production where
         * the families already exist.
         */
        $familyDefaults = [
            'Standard' => 10,
            'Crystal' => 20,
            'Metallic' => 30,
            'Chrome' => 50,
            'Pastel' => 60,
            'Silk' => 70,
        ];

        foreach ($familyDefaults as $name => $sortOrder) {
            TextureFamily::firstOrCreate(['name' => $name], ['sort_order' => $sortOrder]);
        }

        $families = TextureFamily::pluck('id', 'name');

        $textures = [
            ['name' => 'Standard (K)',     'family' => 'Standard', 'sort_order' => 200],
            ['name' => 'Retro (K)',        'family' => 'Standard', 'sort_order' => 210],
            ['name' => 'Macaron (K)',      'family' => 'Pastel',   'sort_order' => 220],
            ['name' => 'Opaque Satin (K)', 'family' => 'Silk',     'sort_order' => 230],
            ['name' => 'Metallic (K)',     'family' => 'Metallic', 'sort_order' => 240],
            ['name' => 'Pearl (K)',        'family' => 'Metallic', 'sort_order' => 250],
            ['name' => 'Crystal (K)',      'family' => 'Crystal',  'sort_order' => 260],
            ['name' => 'Mirror (K)',       'family' => 'Chrome',   'sort_order' => 270],
            ['name' => 'Aura (K)',         'family' => 'Silk',     'sort_order' => 280],
        ];

        foreach ($textures as $data) {
            Texture::firstOrCreate(
                ['name' => $data['name'], 'material_id' => $latex->id, 'brand_id' => $kalisan->id],
                [
                    'texture_family_id' => $families[$data['family']],
                    'sort_order' => $data['sort_order'],
                ],
            );
        }
    }
}
