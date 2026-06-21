<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Color;
use App\Models\ColorFamily;
use App\Models\Material;
use App\Models\Texture;
use Illuminate\Database\Seeder;

/**
 * Additive backfill of Qualatex colors surfaced by the legacy "From_Larry"
 * import that weren't in the original QualatexColorSeeder. Names + family
 * groupings are sourced from the official Qualatex color guide
 * (laballoons.com/pages/latex-colors-by-brand-qualatex); hex values are
 * approximate chart values and can be refined in the catalog UI — the
 * color-family swatch is the fallback.
 *
 * Idempotent (updateOrCreate by name+brand). Also corrects six standard colors
 * that were stranded on the soft-deleted global "Standard" texture, pointing
 * them at the live brand-scoped "Standard (Q)" texture.
 */
class QualatexColorBackfillSeeder extends Seeder
{
    public function run(): void
    {
        $qualatex = Brand::where('name', 'Qualatex')->firstOrFail();
        $latex = Material::where('name', 'Latex')->firstOrFail();

        $standardQ = Texture::where('name', 'Standard (Q)')->where('brand_id', $qualatex->id)->firstOrFail();
        $crystal = Texture::where('name', 'Crystal')->whereNull('brand_id')->firstOrFail();
        $pearl = Texture::where('name', 'Pearl')->whereNull('brand_id')->firstOrFail();
        $chrome = Texture::where('name', 'Chrome')->whereNull('brand_id')->firstOrFail();
        $metallic = Texture::where('name', 'Metallic')->whereNull('brand_id')->firstOrFail();
        $satin = Texture::where('name', 'Satin')->whereNull('brand_id')->firstOrFail();
        $neon = Texture::where('name', 'Neon')->whereNull('brand_id')->firstOrFail();

        $families = ColorFamily::pluck('id', 'name');

        // Repair: six standard colors stranded on the soft-deleted global
        // "Standard" texture → live brand "Standard (Q)".
        Color::where('brand_id', $qualatex->id)
            ->whereIn('name', ['White', 'Black', 'Red', 'Orange', 'Green', 'Blue'])
            ->update(['texture_id' => $standardQ->id]);

        // [name, texture, family, hex]
        $colors = [
            // Standard / Fashion (opaque)
            ['Yellow', $standardQ, 'Yellows', '#FFE11A'],
            ['Gray', $standardQ, 'Silvers', '#97999B'],
            ['Dark Blue', $standardQ, 'Blues', '#003087'],
            ['Navy', $standardQ, 'Blues', '#1B264F'],
            ['Pale Blue', $standardQ, 'Blues', '#B8D8E8'],
            ['Chocolate Brown', $standardQ, 'Browns', '#5C3317'],
            ['Purple Violet', $standardQ, 'Purples', '#5B2C86'],
            ['Periwinkle', $standardQ, 'Purples', '#8E9DCC'],
            ['Spring Lilac', $standardQ, 'Purples', '#C9A0DC'],
            ['Blush', $standardQ, 'Pinks', '#F4C2C2'],
            ['Coral', $standardQ, 'Pinks', '#FF6F61'],
            ['Spring Green', $standardQ, 'Greens', '#9CCB3B'],
            ['Maroon', $standardQ, 'Reds', '#800000'],
            ['Cashmere', $standardQ, 'Whites', '#E8DCC8'],

            // Jewel (transparent — Crystal)
            ['Diamond Clear', $crystal, 'Clears', '#F2F5F5'],
            ['Citrine Yellow', $crystal, 'Yellows', '#F6D108'],
            ['Quartz Purple', $crystal, 'Purples', '#6E4B8E'],
            ['Caribbean Blue', $crystal, 'Blues', '#1CA9C9'],
            ['Tropical Teal', $crystal, 'Blues', '#008E97'],
            ['Sparkling Burgundy', $crystal, 'Reds', '#6E1423'],
            ['Wintergreen', $crystal, 'Greens', '#2E8B57'],

            // Pearl
            ['Pearl Sapphire Blue', $pearl, 'Blues', '#2A4B8D'],
            ['Pearl Azure', $pearl, 'Blues', '#5A8FC2'],
            ['Pearl Midnight Blue', $pearl, 'Blues', '#2C3E66'],
            ['Pearl Teal', $pearl, 'Blues', '#4FA39B'],
            ['Pearl Magenta', $pearl, 'Pinks', '#C24E8E'],
            ['Pearl Fuchsia', $pearl, 'Pinks', '#C74592'],
            ['Pearl Ruby Red', $pearl, 'Reds', '#B03050'],
            ['Pearl Burgundy', $pearl, 'Reds', '#7B2D3A'],
            ['Pearl Emerald Green', $pearl, 'Greens', '#3A9D6E'],
            ['Pearl Mint Green', $pearl, 'Greens', '#AEE0C0'],
            ['Pearl Forest Green', $pearl, 'Greens', '#4F7A52'],
            ['Pearl Sea Green', $pearl, 'Greens', '#6FB3A0'],
            ['Pearl Lime Green', $pearl, 'Greens', '#BBD96B'],
            ['Pearl Lemon Chiffon', $pearl, 'Yellows', '#F5E79E'],
            ['Pearl Mandarin Orange', $pearl, 'Oranges', '#F2864B'],
            ['Pearl Burnt Sienna', $pearl, 'Browns', '#B86B4B'],
            ['Pearl Onyx Black', $pearl, 'Blacks', '#2B2B2B'],
            ['Pearl Black', $pearl, 'Blacks', '#3A3A3A'],

            // Chrome
            ['Chrome Silver', $chrome, 'Silvers', '#C7C9CC'],
            ['Chrome Gold', $chrome, 'Golds', '#D4AF37'],
            ['Chrome Copper', $chrome, 'Oranges', '#B87333'],
            ['Chrome Blue', $chrome, 'Blues', '#5A7DB5'],
            ['Chrome Green', $chrome, 'Greens', '#6FA06B'],
            ['Chrome Purple', $chrome, 'Purples', '#7A5C99'],
            ['Chrome Mauve', $chrome, 'Purples', '#9A7B9B'],
            ['Chrome Rose Gold', $chrome, 'Pinks', '#E6A88A'],

            // Metallic
            ['Rose Gold', $metallic, 'Pinks', '#B76E79'],

            // Silk (Satin)
            ['Ivory Silk', $satin, 'Whites', '#F3EAD3'],
            ['Silk Seafoam', $satin, 'Greens', '#9FD7C2'],

            // Neon
            ['Neon Violet', $neon, 'Purples', '#8A2BE2'],
        ];

        $sortOrder = 600;
        foreach ($colors as [$name, $texture, $family, $hex]) {
            Color::updateOrCreate(
                ['name' => $name, 'brand_id' => $qualatex->id],
                [
                    'color_family_id' => $families[$family] ?? null,
                    'material_id' => $latex->id,
                    'texture_id' => $texture->id,
                    'color_hex' => $hex,
                    'sort_order' => $sortOrder += 10,
                ],
            );
        }
    }
}
