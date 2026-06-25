<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Color;
use App\Models\ColorFamily;
use App\Models\Material;
use App\Models\Texture;
use Illuminate\Database\Seeder;

/**
 * Base solid Britetex colours.
 *
 * Sourced from the distributor (Larocks) staging data, which exposes colour
 * NAMES only — no published hex, swatches, or per-colour finish. The hex values
 * here are sensible approximations so the catalog renders a swatch; they can be
 * refined when a fuller Britetex palette (with images) is imported. The matcher
 * resolves a distributor's "Color" attribute on the name alone, so seeding these
 * is what flips the pending Britetex proposals from partial to full.
 *
 * All base colours default to the Standard (B) texture; other Britetex textures
 * (Crystal/Macaron/Chrome) are reserved for the specialty lines added later.
 */
class BritetexColorSeeder extends Seeder
{
    public function run(): void
    {
        $britetex = Brand::where('name', 'Britetex')->firstOrFail();
        $latex = Material::where('name', 'Latex')->firstOrFail();
        $standard = Texture::where('name', 'Standard (B)')->where('brand_id', $britetex->id)->firstOrFail();

        $families = ColorFamily::pluck('id', 'name');

        foreach ($this->colorData() as $data) {
            Color::updateOrCreate(
                ['name' => $data['name'], 'brand_id' => $britetex->id],
                [
                    'color_family_id' => $families[$data['family']] ?? null,
                    'brand_id' => $britetex->id,
                    'material_id' => $latex->id,
                    'texture_id' => $standard->id,
                    'color_hex' => $data['hex'],
                    'pms_value' => null,
                    'sort_order' => $data['sort_order'],
                ],
            );
        }
    }

    /**
     * The base Britetex solid colours seen in distributor staging.
     *
     * @return array<int, array{name: string, family: string, hex: string, sort_order: int}>
     */
    private function colorData(): array
    {
        return [
            ['name' => 'Blue',   'family' => 'Blues',   'hex' => '#2D6FBF', 'sort_order' => 10],
            ['name' => 'Blush',  'family' => 'Pinks',   'hex' => '#E8B7B0', 'sort_order' => 20],
            ['name' => 'Brown',  'family' => 'Browns',  'hex' => '#6B4A33', 'sort_order' => 30],
            ['name' => 'Gold',   'family' => 'Golds',   'hex' => '#D4AF37', 'sort_order' => 40],
            ['name' => 'Gray',   'family' => 'Blacks',  'hex' => '#9E9EA3', 'sort_order' => 50],
            ['name' => 'Green',  'family' => 'Greens',  'hex' => '#2E9E4F', 'sort_order' => 60],
            ['name' => 'Nude',   'family' => 'Browns',  'hex' => '#E3C4A8', 'sort_order' => 70],
            ['name' => 'Orange', 'family' => 'Oranges', 'hex' => '#F4811F', 'sort_order' => 80],
            ['name' => 'Pink',   'family' => 'Pinks',   'hex' => '#F39FBB', 'sort_order' => 90],
            ['name' => 'Purple', 'family' => 'Purples', 'hex' => '#8E4FC0', 'sort_order' => 100],
            ['name' => 'Red',    'family' => 'Reds',    'hex' => '#E0202C', 'sort_order' => 110],
            ['name' => 'Silver', 'family' => 'Silvers', 'hex' => '#C8CACC', 'sort_order' => 120],
            ['name' => 'White',  'family' => 'Whites',  'hex' => '#F4F4F4', 'sort_order' => 130],
            ['name' => 'Yellow', 'family' => 'Yellows', 'hex' => '#F4D03F', 'sort_order' => 140],
        ];
    }
}
