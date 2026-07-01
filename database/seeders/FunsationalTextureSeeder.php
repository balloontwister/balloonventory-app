<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Material;
use App\Models\Texture;
use App\Models\TextureFamily;
use Illuminate\Database\Seeder;

/**
 * Brand-scoped Funsational textures (the `(F)` suffix, same convention as
 * Kalisan `(K)` / Sempertex `(S)` / Elitex `(E)`).
 *
 * The finishes are sourced from distributor staging (Larocks/LA/Joker). Only the
 * four finishes that actually appear on solid-latex products are created —
 * Standard, Pearl, Crystal, Pastel (the "Neon" staged rows were water balloons,
 * not solids). All families already exist in TextureFamilySeeder (Pearl rolls
 * into Metallic), so the firstOrCreate is a no-op there.
 */
class FunsationalTextureSeeder extends Seeder
{
    public function run(): void
    {
        $funsational = Brand::where('name', 'Funsational')->firstOrFail();
        $latex = Material::where('name', 'Latex')->firstOrFail();

        // Standard/Crystal/Metallic are in TextureFamilySeeder; Pastel is added by
        // the Kalisan/Elitex seeders. firstOrCreate keeps this a no-op on prod
        // (where all four exist) while covering fresh installs / tests.
        $familyDefaults = ['Standard' => 10, 'Crystal' => 20, 'Metallic' => 40, 'Pastel' => 60];

        foreach ($familyDefaults as $name => $sortOrder) {
            TextureFamily::firstOrCreate(['name' => $name], ['sort_order' => $sortOrder]);
        }

        $families = TextureFamily::pluck('id', 'name');

        $textures = [
            ['name' => 'Standard (F)', 'family' => 'Standard', 'sort_order' => 800],
            ['name' => 'Pearl (F)',    'family' => 'Metallic', 'sort_order' => 810],
            ['name' => 'Crystal (F)',  'family' => 'Crystal',  'sort_order' => 820],
            ['name' => 'Pastel (F)',   'family' => 'Pastel',   'sort_order' => 830],
        ];

        foreach ($textures as $data) {
            Texture::firstOrCreate(
                ['name' => $data['name'], 'material_id' => $latex->id, 'brand_id' => $funsational->id],
                [
                    'texture_family_id' => $families[$data['family']],
                    'sort_order' => $data['sort_order'],
                ],
            );
        }
    }
}
