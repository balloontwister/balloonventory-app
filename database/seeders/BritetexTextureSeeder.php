<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Material;
use App\Models\Texture;
use App\Models\TextureFamily;
use Illuminate\Database\Seeder;

class BritetexTextureSeeder extends Seeder
{
    public function run(): void
    {
        $britetex = Brand::where('name', 'Britetex')->firstOrFail();
        $latex = Material::where('name', 'Latex')->firstOrFail();

        /**
         * Texture families used by the Britetex line. Standard, Crystal, and
         * Chrome are already in TextureFamilySeeder; Pastel is added by the
         * Kalisan/Elitex seeders (Macaron rolls into Pastel). firstOrCreate keeps
         * this a no-op on production where the families already exist.
         */
        $familyDefaults = [
            'Standard' => 10,
            'Crystal' => 20,
            'Chrome' => 50,
            'Pastel' => 60,
        ];

        foreach ($familyDefaults as $name => $sortOrder) {
            TextureFamily::firstOrCreate(['name' => $name], ['sort_order' => $sortOrder]);
        }

        $families = TextureFamily::pluck('id', 'name');

        $textures = [
            ['name' => 'Standard (B)', 'family' => 'Standard', 'sort_order' => 800],
            ['name' => 'Crystal (B)',  'family' => 'Crystal',  'sort_order' => 810],
            ['name' => 'Macaron (B)',  'family' => 'Pastel',   'sort_order' => 820],
            ['name' => 'Chrome (B)',   'family' => 'Chrome',   'sort_order' => 830],
        ];

        foreach ($textures as $data) {
            Texture::firstOrCreate(
                ['name' => $data['name'], 'material_id' => $latex->id, 'brand_id' => $britetex->id],
                [
                    'texture_family_id' => $families[$data['family']],
                    'sort_order' => $data['sort_order'],
                ],
            );
        }
    }
}
