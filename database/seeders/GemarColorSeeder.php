<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Color;
use App\Models\ColorFamily;
use App\Models\Material;
use App\Models\Texture;
use Illuminate\Database\Seeder;

class GemarColorSeeder extends Seeder
{
    /**
     * Gemar latex balloon colors sourced from the official Gemar website:
     * https://gemarballoons.com/balloons/colors-sizes-gemar-balloons/
     *
     * 104 colors across 6 finishes: Standard (43), Crystal (10),
     * Crystal Rainbow (5), Neon (6), Metallic (30), Shiny (10).
     *
     * Color names follow the "Name #NNN" convention, with a handful of
     * dual-name colors where Gemar uses different names on product pages
     * vs. the main catalog (e.g. "Jungle Green / Green Olive #098").
     *
     * Hex codes are not yet sampled; they will be added in a follow-up
     * after scraping individual color pages.
     */
    public function run(): void
    {
        $gemar = Brand::where('name', 'Gemar')->firstOrFail();
        $latex = Material::where('name', 'Latex')->firstOrFail();

        $standard = $this->gemarTexture('Standard (G)', $gemar->id);
        $crystal = $this->gemarTexture('Crystal (G)', $gemar->id);
        $crystalRainbow = $this->gemarTexture('Crystal Rainbow (G)', $gemar->id);
        $neon = $this->gemarTexture('Neon (G)', $gemar->id);
        $metallic = $this->gemarTexture('Metallic (G)', $gemar->id);
        $shiny = $this->gemarTexture('Shiny (G)', $gemar->id);

        $families = ColorFamily::pluck('id', 'name');

        $colors = $this->colorData($standard, $crystal, $crystalRainbow, $neon, $metallic, $shiny);

        foreach ($colors as $data) {
            Color::updateOrCreate(
                ['name' => $data['name'], 'brand_id' => $gemar->id],
                [
                    'color_family_id' => $families[$data['family']] ?? null,
                    'brand_id' => $gemar->id,
                    'material_id' => $latex->id,
                    'texture_id' => $data['texture']->id,
                    'color_hex' => $data['hex'] ?? null,
                    'pms_value' => $data['pms'] ?? null,
                    'sort_order' => $data['sort_order'],
                ],
            );
        }
    }

    private function gemarTexture(string $name, string $brandId): Texture
    {
        return Texture::where('name', $name)->where('brand_id', $brandId)->firstOrFail();
    }

    /**
     * Full Gemar latex balloon color catalog.
     *
     * @return array<int, array{name: string, texture: Texture, family: string, hex: ?string, pms: ?string, sort_order: int}>
     */
    private function colorData(
        Texture $standard,
        Texture $crystal,
        Texture $crystalRainbow,
        Texture $neon,
        Texture $metallic,
        Texture $shiny,
    ): array {
        return [

            // ============================================================
            // CRYSTAL — 10 colors (translucent, glossy)
            // ============================================================
            ['name' => 'Blue #051',              'texture' => $crystal, 'family' => 'Blues',      'hex' => null, 'pms' => null, 'sort_order' => 51],
            ['name' => 'Crystal Ass. #087',       'texture' => $crystal, 'family' => 'Assortment', 'hex' => null, 'pms' => null, 'sort_order' => 87],
            ['name' => 'Red #042',               'texture' => $crystal, 'family' => 'Reds',       'hex' => null, 'pms' => null, 'sort_order' => 42],
            ['name' => 'Burgundy #047',          'texture' => $crystal, 'family' => 'Purples',    'hex' => null, 'pms' => null, 'sort_order' => 47],
            ['name' => 'Orange #041',            'texture' => $crystal, 'family' => 'Oranges',    'hex' => null, 'pms' => null, 'sort_order' => 41],
            ['name' => 'Yellow #040',            'texture' => $crystal, 'family' => 'Yellows',    'hex' => null, 'pms' => null, 'sort_order' => 40],
            ['name' => 'Purple #020',            'texture' => $crystal, 'family' => 'Purples',    'hex' => null, 'pms' => null, 'sort_order' => 20],
            ['name' => 'Blue #019',              'texture' => $crystal, 'family' => 'Blues',      'hex' => null, 'pms' => null, 'sort_order' => 19],
            ['name' => 'Green #018',             'texture' => $crystal, 'family' => 'Greens',     'hex' => null, 'pms' => null, 'sort_order' => 18],
            ['name' => 'Crystal #000',           'texture' => $crystal, 'family' => 'Clears',     'hex' => null, 'pms' => null, 'sort_order' => 0],

            // ============================================================
            // CRYSTAL RAINBOW — 5 colors (translucent with rainbow shimmer)
            // ============================================================
            ['name' => 'Sky Blue #044',          'texture' => $crystalRainbow, 'family' => 'Blues',   'hex' => null, 'pms' => null, 'sort_order' => 44],
            ['name' => 'Jade Green #023',        'texture' => $crystalRainbow, 'family' => 'Greens',  'hex' => null, 'pms' => null, 'sort_order' => 23],
            ['name' => 'Lilac #017',             'texture' => $crystalRainbow, 'family' => 'Purples', 'hex' => null, 'pms' => null, 'sort_order' => 17],
            ['name' => 'Pink #016',              'texture' => $crystalRainbow, 'family' => 'Pinks',   'hex' => null, 'pms' => null, 'sort_order' => 16],
            ['name' => 'Yellow #015',            'texture' => $crystalRainbow, 'family' => 'Yellows', 'hex' => null, 'pms' => null, 'sort_order' => 15],

            // ============================================================
            // STANDARD — 43 colors (opaque, classic finish)
            // ============================================================
            ['name' => 'Yellow #002',                        'texture' => $standard, 'family' => 'Yellows',    'hex' => null, 'pms' => null, 'sort_order' => 2],
            ['name' => 'Classic Ass. #080',                   'texture' => $standard, 'family' => 'Assortment', 'hex' => null, 'pms' => null, 'sort_order' => 80],
            ['name' => 'Standard Ass. #086',                  'texture' => $standard, 'family' => 'Assortment', 'hex' => null, 'pms' => null, 'sort_order' => 86],
            ['name' => 'White #001',                         'texture' => $standard, 'family' => 'Whites',     'hex' => null, 'pms' => null, 'sort_order' => 1],
            ['name' => 'Blush #069',                         'texture' => $standard, 'family' => 'Pinks',      'hex' => null, 'pms' => null, 'sort_order' => 69],
            ['name' => 'Ivory #059',                         'texture' => $standard, 'family' => 'Whites',     'hex' => null, 'pms' => null, 'sort_order' => 59],
            ['name' => 'Shell #100',                         'texture' => $standard, 'family' => 'Pinks',      'hex' => null, 'pms' => null, 'sort_order' => 100],
            ['name' => 'Latte #084',                         'texture' => $standard, 'family' => 'Browns',     'hex' => null, 'pms' => null, 'sort_order' => 84],
            ['name' => 'Peach #060',                         'texture' => $standard, 'family' => 'Oranges',    'hex' => null, 'pms' => null, 'sort_order' => 60],
            ['name' => 'Misty Rose #099',                    'texture' => $standard, 'family' => 'Pinks',      'hex' => null, 'pms' => null, 'sort_order' => 99],
            ['name' => 'Butter #103',                        'texture' => $standard, 'family' => 'Yellows',    'hex' => null, 'pms' => null, 'sort_order' => 103],
            ['name' => 'Baby Yellow #043',                   'texture' => $standard, 'family' => 'Yellows',    'hex' => null, 'pms' => null, 'sort_order' => 43],
            ['name' => 'Mango Yellow / Yellow #003',         'texture' => $standard, 'family' => 'Yellows',    'hex' => null, 'pms' => null, 'sort_order' => 3],
            ['name' => 'Orange #004',                        'texture' => $standard, 'family' => 'Oranges',    'hex' => null, 'pms' => null, 'sort_order' => 4],
            ['name' => 'Mocha #076',                         'texture' => $standard, 'family' => 'Browns',     'hex' => null, 'pms' => null, 'sort_order' => 76],
            ['name' => 'Brown #048',                         'texture' => $standard, 'family' => 'Browns',     'hex' => null, 'pms' => null, 'sort_order' => 48],
            ['name' => 'Corallo #078',                       'texture' => $standard, 'family' => 'Pinks',      'hex' => null, 'pms' => null, 'sort_order' => 78],
            ['name' => 'Raspberry Red / Red #005',           'texture' => $standard, 'family' => 'Reds',       'hex' => null, 'pms' => null, 'sort_order' => 5],
            ['name' => 'Red #045',                           'texture' => $standard, 'family' => 'Reds',       'hex' => null, 'pms' => null, 'sort_order' => 45],
            ['name' => 'Rose #006',                          'texture' => $standard, 'family' => 'Pinks',      'hex' => null, 'pms' => null, 'sort_order' => 6],
            ['name' => 'Pink #057',                          'texture' => $standard, 'family' => 'Pinks',      'hex' => null, 'pms' => null, 'sort_order' => 57],
            ['name' => 'Baby Pink #073',                     'texture' => $standard, 'family' => 'Pinks',      'hex' => null, 'pms' => null, 'sort_order' => 73],
            ['name' => 'Fuchsia #007',                       'texture' => $standard, 'family' => 'Pinks',      'hex' => null, 'pms' => null, 'sort_order' => 7],
            ['name' => 'Vino #101',                          'texture' => $standard, 'family' => 'Purples',    'hex' => null, 'pms' => null, 'sort_order' => 101],
            ['name' => 'Lilac #079',                         'texture' => $standard, 'family' => 'Purples',    'hex' => null, 'pms' => null, 'sort_order' => 79],
            ['name' => 'Lavender #049',                      'texture' => $standard, 'family' => 'Purples',    'hex' => null, 'pms' => null, 'sort_order' => 49],
            ['name' => 'Purple #008',                        'texture' => $standard, 'family' => 'Purples',    'hex' => null, 'pms' => null, 'sort_order' => 8],
            ['name' => 'Periwinkle #075',                    'texture' => $standard, 'family' => 'Blues',      'hex' => null, 'pms' => null, 'sort_order' => 75],
            ['name' => 'Baby Blue #072',                     'texture' => $standard, 'family' => 'Blues',      'hex' => null, 'pms' => null, 'sort_order' => 72],
            ['name' => 'Light Blue #009',                    'texture' => $standard, 'family' => 'Blues',      'hex' => null, 'pms' => null, 'sort_order' => 9],
            ['name' => 'Blue #010',                          'texture' => $standard, 'family' => 'Blues',      'hex' => null, 'pms' => null, 'sort_order' => 10],
            ['name' => 'Royal Blue / Blue #046',             'texture' => $standard, 'family' => 'Blues',      'hex' => null, 'pms' => null, 'sort_order' => 46],
            ['name' => 'Navy #102',                          'texture' => $standard, 'family' => 'Blues',      'hex' => null, 'pms' => null, 'sort_order' => 102],
            ['name' => 'Turquoise #068',                     'texture' => $standard, 'family' => 'Blues',      'hex' => null, 'pms' => null, 'sort_order' => 68],
            ['name' => 'Mint Green #077',                    'texture' => $standard, 'family' => 'Greens',     'hex' => null, 'pms' => null, 'sort_order' => 77],
            ['name' => 'Jungle Green / Green Olive #098',    'texture' => $standard, 'family' => 'Greens',     'hex' => null, 'pms' => null, 'sort_order' => 98],
            ['name' => 'Aquamarine #050',                    'texture' => $standard, 'family' => 'Blues',      'hex' => null, 'pms' => null, 'sort_order' => 50],
            ['name' => 'Light Green #011',                   'texture' => $standard, 'family' => 'Greens',     'hex' => null, 'pms' => null, 'sort_order' => 11],
            ['name' => 'Green #012',                         'texture' => $standard, 'family' => 'Greens',     'hex' => null, 'pms' => null, 'sort_order' => 12],
            ['name' => 'Winter Green / Green #013',          'texture' => $standard, 'family' => 'Greens',     'hex' => null, 'pms' => null, 'sort_order' => 13],
            ['name' => 'Emerald Green #104',                 'texture' => $standard, 'family' => 'Greens',     'hex' => null, 'pms' => null, 'sort_order' => 104],
            ['name' => 'Grey #070',                          'texture' => $standard, 'family' => 'Blacks',     'hex' => null, 'pms' => null, 'sort_order' => 70],
            ['name' => 'Black #014',                         'texture' => $standard, 'family' => 'Blacks',     'hex' => null, 'pms' => null, 'sort_order' => 14],

            // ============================================================
            // NEON — 6 colors (vibrant fluorescent)
            // ============================================================
            ['name' => 'Neon Ass. #081',         'texture' => $neon, 'family' => 'Assortment', 'hex' => null, 'pms' => null, 'sort_order' => 81],
            ['name' => 'Yellow #021',            'texture' => $neon, 'family' => 'Yellows',    'hex' => null, 'pms' => null, 'sort_order' => 21],
            ['name' => 'Orange #022',            'texture' => $neon, 'family' => 'Oranges',    'hex' => null, 'pms' => null, 'sort_order' => 22],
            ['name' => 'Pink #025',              'texture' => $neon, 'family' => 'Pinks',      'hex' => null, 'pms' => null, 'sort_order' => 25],
            ['name' => 'Purple #026',            'texture' => $neon, 'family' => 'Purples',    'hex' => null, 'pms' => null, 'sort_order' => 26],
            ['name' => 'Green #027',             'texture' => $neon, 'family' => 'Greens',     'hex' => null, 'pms' => null, 'sort_order' => 27],

            // ============================================================
            // METALLIC — 30 colors (shimmering metallic/pearl)
            // ============================================================
            ['name' => 'Terracotta',                          'texture' => $metallic, 'family' => 'Browns',     'hex' => null, 'pms' => null, 'sort_order' => 999],
            ['name' => 'Metallic Ass. #082',                   'texture' => $metallic, 'family' => 'Assortment', 'hex' => null, 'pms' => null, 'sort_order' => 82],
            ['name' => 'White #029',                          'texture' => $metallic, 'family' => 'Whites',     'hex' => null, 'pms' => null, 'sort_order' => 29],
            ['name' => 'Pearl #028',                          'texture' => $metallic, 'family' => 'Whites',     'hex' => null, 'pms' => null, 'sort_order' => 28],
            ['name' => 'Ivory #058',                          'texture' => $metallic, 'family' => 'Whites',     'hex' => null, 'pms' => null, 'sort_order' => 58],
            ['name' => 'Peach #061',                          'texture' => $metallic, 'family' => 'Oranges',    'hex' => null, 'pms' => null, 'sort_order' => 61],
            ['name' => 'Yellow #030',                         'texture' => $metallic, 'family' => 'Yellows',    'hex' => null, 'pms' => null, 'sort_order' => 30],
            ['name' => 'Dorato #074',                         'texture' => $metallic, 'family' => 'Golds',      'hex' => null, 'pms' => null, 'sort_order' => 74],
            ['name' => 'Gold #039',                           'texture' => $metallic, 'family' => 'Golds',      'hex' => null, 'pms' => null, 'sort_order' => 39],
            ['name' => 'Orange #031',                         'texture' => $metallic, 'family' => 'Oranges',    'hex' => null, 'pms' => null, 'sort_order' => 31],
            ['name' => 'Brown #066',                          'texture' => $metallic, 'family' => 'Browns',     'hex' => null, 'pms' => null, 'sort_order' => 66],
            ['name' => 'Red #032',                            'texture' => $metallic, 'family' => 'Reds',       'hex' => null, 'pms' => null, 'sort_order' => 32],
            ['name' => 'Red #053',                            'texture' => $metallic, 'family' => 'Reds',       'hex' => null, 'pms' => null, 'sort_order' => 53],
            ['name' => 'Rose #033',                           'texture' => $metallic, 'family' => 'Pinks',      'hex' => null, 'pms' => null, 'sort_order' => 33],
            ['name' => 'Fuchsia #064',                        'texture' => $metallic, 'family' => 'Pinks',      'hex' => null, 'pms' => null, 'sort_order' => 64],
            ['name' => 'Rose Gold #071',                      'texture' => $metallic, 'family' => 'Pinks',      'hex' => null, 'pms' => null, 'sort_order' => 71],
            ['name' => 'Lilac #095',                          'texture' => $metallic, 'family' => 'Purples',    'hex' => null, 'pms' => null, 'sort_order' => 95],
            ['name' => 'Burgundy #052',                       'texture' => $metallic, 'family' => 'Purples',    'hex' => null, 'pms' => null, 'sort_order' => 52],
            ['name' => 'Lavender #063',                       'texture' => $metallic, 'family' => 'Purples',    'hex' => null, 'pms' => null, 'sort_order' => 63],
            ['name' => 'Purple #034',                         'texture' => $metallic, 'family' => 'Purples',    'hex' => null, 'pms' => null, 'sort_order' => 34],
            ['name' => 'Light Blue #035',                     'texture' => $metallic, 'family' => 'Blues',      'hex' => null, 'pms' => null, 'sort_order' => 35],
            ['name' => 'Blue #036',                           'texture' => $metallic, 'family' => 'Blues',      'hex' => null, 'pms' => null, 'sort_order' => 36],
            ['name' => 'Ocean Blue / Blue #054',              'texture' => $metallic, 'family' => 'Blues',      'hex' => null, 'pms' => null, 'sort_order' => 54],
            ['name' => 'Aquamarine #062',                     'texture' => $metallic, 'family' => 'Blues',      'hex' => null, 'pms' => null, 'sort_order' => 62],
            ['name' => 'Light Green #067',                    'texture' => $metallic, 'family' => 'Greens',     'hex' => null, 'pms' => null, 'sort_order' => 67],
            ['name' => 'Mint Green #094',                     'texture' => $metallic, 'family' => 'Greens',     'hex' => null, 'pms' => null, 'sort_order' => 94],
            ['name' => 'Green #037',                          'texture' => $metallic, 'family' => 'Greens',     'hex' => null, 'pms' => null, 'sort_order' => 37],
            ['name' => 'Green #055',                          'texture' => $metallic, 'family' => 'Greens',     'hex' => null, 'pms' => null, 'sort_order' => 55],
            ['name' => 'Silver #038',                         'texture' => $metallic, 'family' => 'Silvers',    'hex' => null, 'pms' => null, 'sort_order' => 38],
            ['name' => 'Black #065',                          'texture' => $metallic, 'family' => 'Blacks',     'hex' => null, 'pms' => null, 'sort_order' => 65],

            // ============================================================
            // SHINY — 10 colors (high-gloss, reflective)
            // ============================================================
            ['name' => 'Shiny Prosecco #085',     'texture' => $shiny, 'family' => 'Yellows', 'hex' => null, 'pms' => null, 'sort_order' => 85],
            ['name' => 'Shiny Gold #088',         'texture' => $shiny, 'family' => 'Golds',   'hex' => null, 'pms' => null, 'sort_order' => 88],
            ['name' => 'Shiny Silver #089',       'texture' => $shiny, 'family' => 'Silvers', 'hex' => null, 'pms' => null, 'sort_order' => 89],
            ['name' => 'Shiny Space Grey #090',   'texture' => $shiny, 'family' => 'Blacks',  'hex' => null, 'pms' => null, 'sort_order' => 90],
            ['name' => 'Shiny Pink #091',         'texture' => $shiny, 'family' => 'Pinks',   'hex' => null, 'pms' => null, 'sort_order' => 91],
            ['name' => 'Shiny Blue #092',         'texture' => $shiny, 'family' => 'Blues',   'hex' => null, 'pms' => null, 'sort_order' => 92],
            ['name' => 'Shiny Kiwi #105',         'texture' => $shiny, 'family' => 'Greens',  'hex' => null, 'pms' => null, 'sort_order' => 105],
            ['name' => 'Shiny Green #093',        'texture' => $shiny, 'family' => 'Greens',  'hex' => null, 'pms' => null, 'sort_order' => 93],
            ['name' => 'Shiny Rosegold #096',     'texture' => $shiny, 'family' => 'Pinks',   'hex' => null, 'pms' => null, 'sort_order' => 96],
            ['name' => 'Shiny Purple #097',       'texture' => $shiny, 'family' => 'Purples', 'hex' => null, 'pms' => null, 'sort_order' => 97],
        ];
    }
}
