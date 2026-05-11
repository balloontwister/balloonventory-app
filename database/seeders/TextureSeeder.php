<?php

namespace Database\Seeders;

use App\Models\Texture;
use Illuminate\Database\Seeder;

class TextureSeeder extends Seeder
{
    public function run(): void
    {
        $textures = [
            // Crystal family
            ['name' => 'Crystal',          'texture_family' => 'Crystal',  'sort_order' => 10],
            // Standard family
            ['name' => 'Standard',         'texture_family' => 'Standard', 'sort_order' => 20],
            ['name' => 'Matte',            'texture_family' => 'Standard', 'sort_order' => 30],
            ['name' => 'Glow-in-the-dark', 'texture_family' => 'Standard', 'sort_order' => 40],
            // Metallic family
            ['name' => 'Metallic',         'texture_family' => 'Metallic', 'sort_order' => 50],
            ['name' => 'Pearl',            'texture_family' => 'Metallic', 'sort_order' => 60],
            // Neon family
            ['name' => 'Neon',             'texture_family' => 'Neon',     'sort_order' => 70],
            // Chrome family
            ['name' => 'Chrome',           'texture_family' => 'Chrome',   'sort_order' => 80],
            ['name' => 'Satin',            'texture_family' => 'Chrome',   'sort_order' => 90],
        ];

        foreach ($textures as $data) {
            Texture::firstOrCreate(['name' => $data['name']], $data);
        }
    }
}
