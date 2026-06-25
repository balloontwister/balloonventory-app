<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Material;
use App\Models\Texture;
use App\Models\TextureFamily;
use Illuminate\Database\Seeder;

class ElitexTextureSeeder extends Seeder
{
    public function run(): void
    {
        $elitex = Brand::where('name', 'Elitex')->firstOrFail();
        $latex = Material::where('name', 'Latex')->firstOrFail();

        // Ensure all families used by Elitex textures exist. Standard, Crystal,
        // Metallic, and Chrome come from TextureFamilySeeder; Pastel is added
        // by KalisanTextureSeeder — use firstOrCreate so this seeder is
        // self-contained in test environments.
        $familyDefaults = [
            'Standard' => 10,
            'Crystal' => 20,
            'Metallic' => 30,
            'Chrome' => 50,
            'Pastel' => 60,
        ];

        foreach ($familyDefaults as $name => $sortOrder) {
            TextureFamily::firstOrCreate(['name' => $name], ['sort_order' => $sortOrder]);
        }

        $families = TextureFamily::pluck('id', 'name');

        $textures = [
            ['name' => 'Standard (E)',        'family' => 'Standard', 'sort_order' => 705],
            ['name' => 'Metallic & Pearl (E)', 'family' => 'Metallic', 'sort_order' => 710],
            ['name' => 'Pastel Rainbow (E)',   'family' => 'Pastel',   'sort_order' => 715],
            ['name' => 'Smoothies (E)',        'family' => 'Pastel',   'sort_order' => 720],
            ['name' => 'Super Glow (E)',       'family' => 'Chrome',   'sort_order' => 725],
            ['name' => 'Confetti (E)',         'family' => 'Crystal',  'sort_order' => 730],
        ];

        foreach ($textures as $data) {
            Texture::firstOrCreate(
                ['name' => $data['name'], 'material_id' => $latex->id, 'brand_id' => $elitex->id],
                [
                    'texture_family_id' => $families[$data['family']],
                    'sort_order' => $data['sort_order'],
                ],
            );
        }
    }
}
