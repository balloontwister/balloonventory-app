<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Material;
use App\Models\Texture;
use App\Models\TextureFamily;
use Illuminate\Database\Seeder;

class TextureSeeder extends Seeder
{
    public function run(): void
    {
        $latex = Material::where('name', 'Latex')->first();
        $standardFam = TextureFamily::where('name', 'Standard')->first();
        $crystalFam = TextureFamily::where('name', 'Crystal')->first();
        $metallicFam = TextureFamily::where('name', 'Metallic')->first();
        $neonFam = TextureFamily::where('name', 'Neon')->first();
        $chromeFam = TextureFamily::where('name', 'Chrome')->first();

        // Brand-agnostic latex textures (brand_id = null).
        $textures = [
            ['name' => 'Crystal',          'material_id' => $latex?->id, 'texture_family_id' => $crystalFam?->id,  'sort_order' => 10],
            ['name' => 'Standard',         'material_id' => $latex?->id, 'texture_family_id' => $standardFam?->id, 'sort_order' => 20],
            ['name' => 'Designer',         'material_id' => $latex?->id, 'texture_family_id' => $standardFam?->id, 'sort_order' => 22],
            ['name' => 'Pastel',           'material_id' => $latex?->id, 'texture_family_id' => $standardFam?->id, 'sort_order' => 25],
            ['name' => 'Matte',            'material_id' => $latex?->id, 'texture_family_id' => $standardFam?->id, 'sort_order' => 30],
            ['name' => 'Glow-in-the-dark', 'material_id' => $latex?->id, 'texture_family_id' => $standardFam?->id, 'sort_order' => 40],
            ['name' => 'Metallic',         'material_id' => $latex?->id, 'texture_family_id' => $metallicFam?->id, 'sort_order' => 50],
            ['name' => 'Pearl',            'material_id' => $latex?->id, 'texture_family_id' => $metallicFam?->id, 'sort_order' => 60],
            ['name' => 'Effects',          'material_id' => $latex?->id, 'texture_family_id' => $metallicFam?->id, 'sort_order' => 65],
            ['name' => 'Neon',             'material_id' => $latex?->id, 'texture_family_id' => $neonFam?->id,     'sort_order' => 70],
            ['name' => 'Chrome',           'material_id' => $latex?->id, 'texture_family_id' => $chromeFam?->id,   'sort_order' => 80],
            ['name' => 'Satin',            'material_id' => $latex?->id, 'texture_family_id' => $chromeFam?->id,   'sort_order' => 90],
        ];

        foreach ($textures as $data) {
            Texture::firstOrCreate(
                ['name' => $data['name'], 'material_id' => $data['material_id'], 'brand_id' => null],
                $data,
            );
        }
    }
}
