<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Color;
use App\Models\ColorFamily;
use App\Models\Material;
use App\Models\Texture;
use Illuminate\Database\Seeder;

class TufTexColorSeeder extends Seeder
{
    public function run(): void
    {
        $tuftex = Brand::where('name', 'TufTex')->firstOrFail();
        $latex = Material::where('name', 'Latex')->firstOrFail();

        $designer = Texture::where('name', 'Designer')->whereNull('brand_id')->firstOrFail();
        $standard = Texture::where('name', 'Standard')->whereNull('brand_id')->firstOrFail();
        $crystal = Texture::where('name', 'Crystal')->whereNull('brand_id')->firstOrFail();
        $metallic = Texture::where('name', 'Metallic')->whereNull('brand_id')->firstOrFail();
        $pearl = Texture::where('name', 'Pearl')->whereNull('brand_id')->firstOrFail();
        $effects = Texture::where('name', 'Effects')->whereNull('brand_id')->firstOrFail();

        $families = ColorFamily::pluck('id', 'name');

        $colors = [
            // Designer — TufTex's curated fashion/decorator line
            ['name' => 'Lime Green',    'texture' => $designer, 'family' => 'Greens',  'hex' => '#C8E400', 'pms' => 'PMS 382U',    'sort_order' => 10],
            ['name' => 'Goldenrod',     'texture' => $designer, 'family' => 'Yellows', 'hex' => '#FFCC00', 'pms' => 'PMS 129 C',   'sort_order' => 20],
            ['name' => 'Lemonade',      'texture' => $designer, 'family' => 'Yellows', 'hex' => '#FFE88A', 'pms' => 'PMS 0131C',   'sort_order' => 30],
            ['name' => 'Mustard',       'texture' => $designer, 'family' => 'Yellows', 'hex' => '#F0AA00', 'pms' => 'PMS 7405 C',  'sort_order' => 40],
            ['name' => 'Coral',         'texture' => $designer, 'family' => 'Oranges', 'hex' => '#F07163', 'pms' => 'PMS 177 C',   'sort_order' => 50],
            ['name' => 'Navy',          'texture' => $designer, 'family' => 'Blues',   'hex' => '#1C2B4A', 'pms' => 'PMS 7693 C',  'sort_order' => 60],
            ['name' => 'Baby Pink',     'texture' => $designer, 'family' => 'Pinks',   'hex' => '#F9BCCC', 'pms' => 'PMS 1895 C',  'sort_order' => 70],
            ['name' => 'Baby Blue',     'texture' => $designer, 'family' => 'Blues',   'hex' => '#9DC9D6', 'pms' => 'PMS 2905 C',  'sort_order' => 80],
            ['name' => 'Muse',          'texture' => $designer, 'family' => 'Browns',  'hex' => '#B09A87', 'pms' => 'PMS 4246 C',  'sort_order' => 90],
            ['name' => 'Pixie',         'texture' => $designer, 'family' => 'Pinks',   'hex' => '#E8608A', 'pms' => 'PMS 218 C',   'sort_order' => 100],
            ['name' => 'Royalty',       'texture' => $designer, 'family' => 'Blues',   'hex' => '#003DA6', 'pms' => 'PMS 2935 C',  'sort_order' => 110],
            ['name' => 'Sea Glass',     'texture' => $designer, 'family' => 'Blues',   'hex' => '#9DCCD8', 'pms' => 'PMS 2905C',   'sort_order' => 120],
            ['name' => 'Turquoise',     'texture' => $designer, 'family' => 'Blues',   'hex' => '#009EC4', 'pms' => 'PMS 312 C',   'sort_order' => 130],
            ['name' => 'Blue Slate',    'texture' => $designer, 'family' => 'Blues',   'hex' => '#5070A0', 'pms' => 'PMS 7695 C',  'sort_order' => 140],
            ['name' => 'Malted',        'texture' => $designer, 'family' => 'Browns',  'hex' => '#C5B49A', 'pms' => 'PMS 2474 C',  'sort_order' => 150],
            ['name' => 'Stone',         'texture' => $designer, 'family' => 'Browns',  'hex' => '#C5B9A7', 'pms' => 'PMS 7534 C',  'sort_order' => 160],
            ['name' => 'Scarlett',      'texture' => $designer, 'family' => 'Reds',    'hex' => '#C8102E', 'pms' => 'PMS 194 C',   'sort_order' => 170],
            ['name' => 'Canyon Rose',   'texture' => $designer, 'family' => 'Pinks',   'hex' => '#C48080', 'pms' => 'PMS 4081 C',  'sort_order' => 180],
            ['name' => 'Lavender',      'texture' => $designer, 'family' => 'Purples', 'hex' => '#C9A8D8', 'pms' => 'PMS 2577 C',  'sort_order' => 190],
            ['name' => 'Aloha',         'texture' => $designer, 'family' => 'Oranges', 'hex' => '#D66A40', 'pms' => 'PMS 2348 C',  'sort_order' => 200],
            ['name' => 'Black',         'texture' => $designer, 'family' => 'Blacks',  'hex' => '#231F20', 'pms' => null,          'sort_order' => 210],
            ['name' => 'Willow',        'texture' => $designer, 'family' => 'Greens',  'hex' => '#9EADA3', 'pms' => 'PMS 624 C',   'sort_order' => 220],
            ['name' => 'Blush',         'texture' => $designer, 'family' => 'Pinks',   'hex' => '#EDD5B4', 'pms' => 'PMS 155 C',   'sort_order' => 230],
            ['name' => 'Cheeky',        'texture' => $designer, 'family' => 'Oranges', 'hex' => '#F9B87A', 'pms' => 'PMS 162 C',   'sort_order' => 240],
            ['name' => 'Money',         'texture' => $designer, 'family' => 'Greens',  'hex' => '#4A7C59', 'pms' => null,          'sort_order' => 250],
            ['name' => 'Cameo',         'texture' => $designer, 'family' => 'Pinks',   'hex' => '#D4A5A5', 'pms' => 'PMS 691 C',   'sort_order' => 260],
            ['name' => 'Fiona',         'texture' => $designer, 'family' => 'Greens',  'hex' => '#9B9570', 'pms' => 'PMS 4212 C',  'sort_order' => 270],
            ['name' => 'Empower Mint',  'texture' => $designer, 'family' => 'Greens',  'hex' => '#8DC3AC', 'pms' => 'PMS 559 C',   'sort_order' => 280],
            ['name' => 'Plum Purple',   'texture' => $designer, 'family' => 'Purples', 'hex' => '#6B2B88', 'pms' => 'PMS 2597 C',  'sort_order' => 290],
            ['name' => 'Taffy',         'texture' => $designer, 'family' => 'Pinks',   'hex' => '#F0688A', 'pms' => 'PMS 1777 C',  'sort_order' => 300],
            ['name' => 'Burnt Orange',  'texture' => $designer, 'family' => 'Oranges', 'hex' => '#C25B0A', 'pms' => 'PMS 159 C',   'sort_order' => 310],
            ['name' => 'Lace',          'texture' => $designer, 'family' => 'Whites',  'hex' => '#F5F0E6', 'pms' => 'PMS 11-4302', 'sort_order' => 320],
            ['name' => 'Cocoa',         'texture' => $designer, 'family' => 'Browns',  'hex' => '#8C5636', 'pms' => 'PMS 4635U',   'sort_order' => 330],
            ['name' => 'Samba',         'texture' => $designer, 'family' => 'Reds',    'hex' => '#9C2137', 'pms' => 'PMS 195 C',   'sort_order' => 340],
            ['name' => 'Evergreen',     'texture' => $designer, 'family' => 'Greens',  'hex' => '#007A4D', 'pms' => 'PMS 342 C',   'sort_order' => 350],
            ['name' => 'Gray Smoke',    'texture' => $designer, 'family' => 'Blacks',  'hex' => '#9EA3A5', 'pms' => 'PMS Cool Gray 6 C', 'sort_order' => 360],
            ['name' => 'Fog',           'texture' => $designer, 'family' => 'Blues',   'hex' => '#8699A6', 'pms' => 'PMS 5445 C',  'sort_order' => 370],
            ['name' => 'Hot Pink',      'texture' => $designer, 'family' => 'Pinks',   'hex' => '#DC0060', 'pms' => 'PMS 219 C',   'sort_order' => 380],
            ['name' => 'Blossom',       'texture' => $designer, 'family' => 'Pinks',   'hex' => '#D898C8', 'pms' => 'PMS 2092 C',  'sort_order' => 390],
            ['name' => 'Teal',          'texture' => $designer, 'family' => 'Blues',   'hex' => '#00A878', 'pms' => 'PMS 326 C',   'sort_order' => 400],
            ['name' => 'Peri',          'texture' => $designer, 'family' => 'Blues',   'hex' => '#7A88C8', 'pms' => 'PMS 2131 C',  'sort_order' => 410],
            ['name' => 'Sangria',       'texture' => $designer, 'family' => 'Reds',    'hex' => '#7A2040', 'pms' => 'PMS 4075 C',  'sort_order' => 420],
            ['name' => 'Naval',         'texture' => $designer, 'family' => 'Blues',   'hex' => '#1A2948', 'pms' => 'PMS 7694 C',  'sort_order' => 430],

            // Standard — classic solid opaque colors
            ['name' => 'Red',           'texture' => $standard, 'family' => 'Reds',    'hex' => '#CE1126', 'pms' => 'PMS 185 C',   'sort_order' => 440],
            ['name' => 'White',         'texture' => $standard, 'family' => 'Whites',  'hex' => '#FFFFFF', 'pms' => null,          'sort_order' => 450],
            ['name' => 'Green',         'texture' => $standard, 'family' => 'Greens',  'hex' => '#44A12B', 'pms' => 'PMS 362 C',   'sort_order' => 460],
            ['name' => 'Orange',        'texture' => $standard, 'family' => 'Oranges', 'hex' => '#FF6A13', 'pms' => 'PMS 1585 C',  'sort_order' => 470],
            ['name' => 'Blue',          'texture' => $standard, 'family' => 'Blues',   'hex' => '#009FDB', 'pms' => 'PMS 2925 C',  'sort_order' => 480],
            ['name' => 'Pink',          'texture' => $standard, 'family' => 'Pinks',   'hex' => '#E8698A', 'pms' => 'PMS 203 C',   'sort_order' => 490],
            ['name' => 'Yellow',        'texture' => $standard, 'family' => 'Yellows', 'hex' => '#FFCD00', 'pms' => 'PMS 108 C',   'sort_order' => 500],

            // Crystal — transparent
            ['name' => 'Crystal Red',   'texture' => $crystal,  'family' => 'Reds',    'hex' => '#D32B1E', 'pms' => 'PMS 1795 C',  'sort_order' => 510],
            ['name' => 'Crystal Yellow', 'texture' => $crystal,  'family' => 'Yellows', 'hex' => '#EEE020', 'pms' => 'PMS 3945 C',  'sort_order' => 520],
            ['name' => 'Emerald Green', 'texture' => $crystal,  'family' => 'Greens',  'hex' => '#007A3D', 'pms' => 'PMS 348 C',   'sort_order' => 530],
            ['name' => 'Sapphire Blue', 'texture' => $crystal,  'family' => 'Blues',   'hex' => '#005E8E', 'pms' => 'PMS 301 C',   'sort_order' => 540],
            ['name' => 'Crystal Purple', 'texture' => $crystal,  'family' => 'Purples', 'hex' => '#640F6B', 'pms' => 'PMS 2607 C',  'sort_order' => 550],
            ['name' => 'Clear',         'texture' => $crystal,  'family' => 'Clears',  'hex' => null,      'pms' => null,          'sort_order' => 560],
            ['name' => 'Magenta',       'texture' => $crystal,  'family' => 'Pinks',   'hex' => '#DB3EB1', 'pms' => 'PMS 226 C',   'sort_order' => 570],
            ['name' => 'Burgundy',      'texture' => $crystal,  'family' => 'Reds',    'hex' => '#8F1D3F', 'pms' => 'PMS 221 C',   'sort_order' => 580],

            // Metallic
            ['name' => 'Starfire Red',  'texture' => $metallic, 'family' => 'Reds',    'hex' => '#BE2035', 'pms' => 'PMS 193 C',   'sort_order' => 590],
            ['name' => 'Rose Gold',     'texture' => $metallic, 'family' => 'Pinks',   'hex' => '#D49080', 'pms' => 'PMS 486 C',   'sort_order' => 600],
            ['name' => 'Gold',          'texture' => $metallic, 'family' => 'Golds',   'hex' => '#C69214', 'pms' => 'PMS 131 C',   'sort_order' => 610],
            ['name' => 'Metallic Green', 'texture' => $metallic, 'family' => 'Greens',  'hex' => '#44A12B', 'pms' => 'PMS 362 C',   'sort_order' => 620],
            ['name' => 'Metallic Teal', 'texture' => $metallic, 'family' => 'Blues',   'hex' => '#009CA6', 'pms' => 'PMS 321 C',   'sort_order' => 630],
            ['name' => 'Metallic Blue', 'texture' => $metallic, 'family' => 'Blues',   'hex' => '#0057A8', 'pms' => 'PMS 300 C',   'sort_order' => 640],
            ['name' => 'Forest Green',  'texture' => $metallic, 'family' => 'Greens',  'hex' => '#215732', 'pms' => 'PMS 349 C',   'sort_order' => 650],
            ['name' => 'Seafoam',       'texture' => $metallic, 'family' => 'Blues',   'hex' => '#5DCFBF', 'pms' => 'PMS 3252 C',  'sort_order' => 660],
            ['name' => 'Midnight Blue', 'texture' => $metallic, 'family' => 'Blues',   'hex' => '#1B3D6B', 'pms' => 'PMS 295 C',   'sort_order' => 670],
            ['name' => 'Silver',        'texture' => $metallic, 'family' => 'Silvers', 'hex' => '#A8A9AD', 'pms' => 'PMS Cool Gray C', 'sort_order' => 680],

            // Pearl — pearlescent sheen (hex is dominant hue only)
            ['name' => 'Meadow',        'texture' => $pearl,    'family' => 'Greens',  'hex' => '#6D9B77', 'pms' => 'PMS 2260 C',  'sort_order' => 690],
            ['name' => 'Georgia',       'texture' => $pearl,    'family' => 'Blues',   'hex' => '#7BA4C9', 'pms' => 'PMS 542 C',   'sort_order' => 700],
            ['name' => 'Sugar',         'texture' => $pearl,    'family' => 'Whites',  'hex' => '#F5EEE8', 'pms' => null,          'sort_order' => 710],
            ['name' => 'Pearl Lace',    'texture' => $pearl,    'family' => 'Whites',  'hex' => '#F5F0E6', 'pms' => 'PMS 11-4302', 'sort_order' => 720],
            ['name' => 'Romey',         'texture' => $pearl,    'family' => 'Pinks',   'hex' => '#E8A89C', 'pms' => 'PMS 496 C',   'sort_order' => 730],
            ['name' => 'Shimmering Pink', 'texture' => $pearl,   'family' => 'Pinks',   'hex' => '#E8698A', 'pms' => 'PMS 203 C',   'sort_order' => 740],
            ['name' => 'Fuchsia',       'texture' => $pearl,    'family' => 'Pinks',   'hex' => '#DB3EB1', 'pms' => 'PMS 226 C',   'sort_order' => 750],

            // Effects — specialty metallic-effect finish
            ['name' => 'Shadow',        'texture' => $effects,  'family' => 'Blacks',  'hex' => '#555859', 'pms' => 'PMS 426 C',   'sort_order' => 760],
            ['name' => 'Golden',        'texture' => $effects,  'family' => 'Golds',   'hex' => '#A67C00', 'pms' => 'PMS 871 C',   'sort_order' => 770],
            ['name' => 'Rockstar Pink', 'texture' => $effects,  'family' => 'Pinks',   'hex' => '#EDB4BE', 'pms' => 'PMS 7433 C',  'sort_order' => 780],
            ['name' => 'Silvery',       'texture' => $effects,  'family' => 'Silvers', 'hex' => '#8E9295', 'pms' => 'PMS 877 C',   'sort_order' => 790],
        ];

        foreach ($colors as $color) {
            Color::updateOrCreate(
                ['name' => $color['name'], 'brand_id' => $tuftex->id],
                [
                    'color_family_id' => $families[$color['family']],
                    'brand_id' => $tuftex->id,
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
