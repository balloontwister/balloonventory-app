<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Color;
use App\Models\ColorFamily;
use App\Models\Material;
use App\Models\Texture;
use Illuminate\Database\Seeder;

class QualatexColorSeeder extends Seeder
{
    public function run(): void
    {
        $qualatex = Brand::where('name', 'Qualatex')->firstOrFail();
        $latex = Material::where('name', 'Latex')->firstOrFail();

        $crystal = Texture::where('name', 'Crystal')->whereNull('brand_id')->firstOrFail();
        $standard = Texture::where('name', 'Standard')->whereNull('brand_id')->firstOrFail();
        $pearl = Texture::where('name', 'Pearl')->whereNull('brand_id')->firstOrFail();
        $neon = Texture::where('name', 'Neon')->whereNull('brand_id')->firstOrFail();
        $metallic = Texture::where('name', 'Metallic')->whereNull('brand_id')->firstOrFail();

        $families = ColorFamily::pluck('id', 'name');

        $colors = [
            // Jewel (transparent — Crystal texture)
            ['name' => 'Ruby Red',        'texture' => $crystal,  'family' => 'Reds',    'hex' => '#BE2035', 'pms' => 'PMS 193 C',       'sort_order' => 10],
            ['name' => 'Mandarin Orange', 'texture' => $crystal,  'family' => 'Oranges', 'hex' => '#FE5000', 'pms' => 'PMS 021 C',       'sort_order' => 20],
            ['name' => 'Goldenrod',       'texture' => $crystal,  'family' => 'Yellows', 'hex' => '#FFCD00', 'pms' => 'PMS 116 C',       'sort_order' => 30],
            ['name' => 'Lime Green',      'texture' => $crystal,  'family' => 'Greens',  'hex' => '#78D64B', 'pms' => 'PMS 375 C',       'sort_order' => 40],
            ['name' => 'Forest Green',    'texture' => $crystal,  'family' => 'Greens',  'hex' => '#44A12B', 'pms' => 'PMS 362 C',       'sort_order' => 50],
            ['name' => 'Emerald Green',   'texture' => $crystal,  'family' => 'Greens',  'hex' => '#009A44', 'pms' => 'PMS 347 C',       'sort_order' => 60],
            ['name' => "Robin's Egg",     'texture' => $crystal,  'family' => 'Blues',   'hex' => '#6DCFF6', 'pms' => 'PMS 318 C',       'sort_order' => 70],
            ['name' => 'Teal',            'texture' => $crystal,  'family' => 'Blues',   'hex' => '#009CA6', 'pms' => 'PMS 321 C',       'sort_order' => 80],
            ['name' => 'Crystal Blue',    'texture' => $crystal,  'family' => 'Blues',   'hex' => '#00B5E2', 'pms' => 'PMS 306 C',       'sort_order' => 90],
            ['name' => 'Sapphire Blue',   'texture' => $crystal,  'family' => 'Blues',   'hex' => '#003DA5', 'pms' => 'PMS 286 C',       'sort_order' => 100],
            ['name' => 'Cobalt Blue',     'texture' => $crystal,  'family' => 'Blues',   'hex' => '#003087', 'pms' => 'PMS 280 C',       'sort_order' => 110],
            ['name' => 'Violet',          'texture' => $crystal,  'family' => 'Purples', 'hex' => '#62205F', 'pms' => 'PMS 259 C',       'sort_order' => 120],
            ['name' => 'Wild Berry',      'texture' => $crystal,  'family' => 'Purples', 'hex' => '#7C4D79', 'pms' => 'PMS 256 C',       'sort_order' => 130],
            ['name' => 'Magenta',         'texture' => $crystal,  'family' => 'Pinks',   'hex' => '#DB3EB1', 'pms' => 'PMS 226 C',       'sort_order' => 140],
            ['name' => 'Rose',            'texture' => $crystal,  'family' => 'Pinks',   'hex' => '#F26B8A', 'pms' => 'PMS 197 C',       'sort_order' => 150],

            // Standard / Fashion (opaque)
            ['name' => 'White',           'texture' => $standard, 'family' => 'Whites',  'hex' => '#FFFFFF', 'pms' => null,              'sort_order' => 160],
            ['name' => 'Black',           'texture' => $standard, 'family' => 'Blacks',  'hex' => '#231F20', 'pms' => 'Pantone Black C', 'sort_order' => 170],
            ['name' => 'Red',             'texture' => $standard, 'family' => 'Reds',    'hex' => '#CE1126', 'pms' => 'PMS 186 C',       'sort_order' => 180],
            ['name' => 'Orange',          'texture' => $standard, 'family' => 'Oranges', 'hex' => '#FE5000', 'pms' => 'PMS 021 C',       'sort_order' => 190],
            ['name' => 'Golden Yellow',   'texture' => $standard, 'family' => 'Yellows', 'hex' => '#FFCD00', 'pms' => 'PMS 109 C',       'sort_order' => 200],
            ['name' => 'Green',           'texture' => $standard, 'family' => 'Greens',  'hex' => '#44A12B', 'pms' => 'PMS 362 C',       'sort_order' => 210],
            ['name' => 'Blue',            'texture' => $standard, 'family' => 'Blues',   'hex' => '#003DA5', 'pms' => 'PMS 286 C',       'sort_order' => 220],
            ['name' => 'Purple',          'texture' => $standard, 'family' => 'Purples', 'hex' => '#7C5ABE', 'pms' => 'PMS 2587 C',      'sort_order' => 230],
            ['name' => 'Pink',            'texture' => $standard, 'family' => 'Pinks',   'hex' => '#F26B8A', 'pms' => 'PMS 197 C',       'sort_order' => 240],
            ['name' => 'Ivory',           'texture' => $standard, 'family' => 'Whites',  'hex' => '#FFF5DC', 'pms' => null,              'sort_order' => 250],
            ['name' => 'Mocha Brown',     'texture' => $standard, 'family' => 'Browns',  'hex' => '#803D0A', 'pms' => 'PMS 469 C',       'sort_order' => 260],

            // Pastel (opaque — Standard texture, no dedicated pastel texture exists)
            ['name' => 'Pastel Yellow',   'texture' => $standard, 'family' => 'Yellows', 'hex' => '#FFF5B0', 'pms' => null,              'sort_order' => 270],
            ['name' => 'Pastel Pink',     'texture' => $standard, 'family' => 'Pinks',   'hex' => '#FFDDEE', 'pms' => null,              'sort_order' => 280],
            ['name' => 'Pastel Blue',     'texture' => $standard, 'family' => 'Blues',   'hex' => '#CCE8FF', 'pms' => null,              'sort_order' => 290],
            ['name' => 'Pastel Green',    'texture' => $standard, 'family' => 'Greens',  'hex' => '#CCEECC', 'pms' => null,              'sort_order' => 300],
            ['name' => 'Pastel Lavender', 'texture' => $standard, 'family' => 'Purples', 'hex' => '#E8DDFF', 'pms' => null,              'sort_order' => 310],
            ['name' => 'Pastel Peach',    'texture' => $standard, 'family' => 'Oranges', 'hex' => '#FFE0CC', 'pms' => null,              'sort_order' => 320],

            // Pearl
            ['name' => 'Pearl White',      'texture' => $pearl,   'family' => 'Whites',  'hex' => '#F5F0E8', 'pms' => null,              'sort_order' => 330],
            ['name' => 'Pearl Ivory',      'texture' => $pearl,   'family' => 'Whites',  'hex' => '#F5E6C8', 'pms' => null,              'sort_order' => 340],
            ['name' => 'Pearl Pink',       'texture' => $pearl,   'family' => 'Pinks',   'hex' => '#F7C5D5', 'pms' => null,              'sort_order' => 350],
            ['name' => 'Pearl Blush',      'texture' => $pearl,   'family' => 'Pinks',   'hex' => '#F5B8C4', 'pms' => null,              'sort_order' => 360],
            ['name' => 'Pearl Lavender',   'texture' => $pearl,   'family' => 'Purples', 'hex' => '#C9B8D9', 'pms' => null,              'sort_order' => 370],
            ['name' => 'Pearl Light Blue', 'texture' => $pearl,   'family' => 'Blues',   'hex' => '#B8D4E8', 'pms' => null,              'sort_order' => 380],
            ['name' => 'Pearl Blue',       'texture' => $pearl,   'family' => 'Blues',   'hex' => '#6699CC', 'pms' => null,              'sort_order' => 390],
            ['name' => 'Pearl Turquoise',  'texture' => $pearl,   'family' => 'Blues',   'hex' => '#66CCBB', 'pms' => null,              'sort_order' => 400],
            ['name' => 'Pearl Aqua',       'texture' => $pearl,   'family' => 'Blues',   'hex' => '#99DDDD', 'pms' => null,              'sort_order' => 410],
            ['name' => 'Pearl Green',      'texture' => $pearl,   'family' => 'Greens',  'hex' => '#99CC99', 'pms' => null,              'sort_order' => 420],
            ['name' => 'Pearl Lime',       'texture' => $pearl,   'family' => 'Greens',  'hex' => '#CCEE88', 'pms' => null,              'sort_order' => 430],
            ['name' => 'Pearl Peach',      'texture' => $pearl,   'family' => 'Oranges', 'hex' => '#F5C8A8', 'pms' => null,              'sort_order' => 440],
            ['name' => 'Pearl Coral',      'texture' => $pearl,   'family' => 'Reds',    'hex' => '#F5A8A0', 'pms' => null,              'sort_order' => 450],
            ['name' => 'Pearl Rose',       'texture' => $pearl,   'family' => 'Pinks',   'hex' => '#E88898', 'pms' => null,              'sort_order' => 460],

            // Neon
            ['name' => 'Neon Pink',        'texture' => $neon,    'family' => 'Pinks',   'hex' => '#FF1DCE', 'pms' => null,              'sort_order' => 470],
            ['name' => 'Neon Green',       'texture' => $neon,    'family' => 'Greens',  'hex' => '#39FF14', 'pms' => null,              'sort_order' => 480],
            ['name' => 'Neon Yellow',      'texture' => $neon,    'family' => 'Yellows', 'hex' => '#FFF700', 'pms' => null,              'sort_order' => 490],
            ['name' => 'Neon Orange',      'texture' => $neon,    'family' => 'Oranges', 'hex' => '#FF6700', 'pms' => null,              'sort_order' => 500],
            ['name' => 'Neon Purple',      'texture' => $neon,    'family' => 'Purples', 'hex' => '#CC00FF', 'pms' => null,              'sort_order' => 510],

            // Metallic
            ['name' => 'Gold',             'texture' => $metallic, 'family' => 'Golds',   'hex' => '#C8A951', 'pms' => null,              'sort_order' => 520],
            ['name' => 'Silver',           'texture' => $metallic, 'family' => 'Silvers', 'hex' => '#A8A9AD', 'pms' => null,              'sort_order' => 530],
        ];

        foreach ($colors as $color) {
            Color::updateOrCreate(
                ['name' => $color['name'], 'brand_id' => $qualatex->id],
                [
                    'color_family_id' => $families[$color['family']],
                    'brand_id' => $qualatex->id,
                    'material_id' => $latex->id,
                    'texture_id' => $color['texture']->id,
                    'color_hex' => $color['hex'],
                    'pms_value' => $color['pms'],
                    'sort_order' => $color['sort_order'],
                ],
            );
        }
    }
}
