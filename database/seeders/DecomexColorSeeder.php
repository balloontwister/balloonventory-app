<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Color;
use App\Models\ColorFamily;
use App\Models\Material;
use App\Models\Texture;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class DecomexColorSeeder extends Seeder
{
    /**
     * Decomex latex balloon colors sourced from the official color & size table:
     * https://www.decomex.com/pages/decomex-color-and-size-table
     *
     * Hex values are sampled from the center of each color's collection hero
     * image on the Decomex Shopify storefront (cdn.shopify.com). PMS codes are
     * not published on the Decomex page.
     *
     * Color names follow the "number - name" convention (e.g. "100 - Standard White").
     *
     * 130 colors across 5 finishes: Standard (19), Pastel Deco (62),
     * Jewel Crystal (6), Pearl/Metallic (36), Luster (7).
     *
     * Single-balloon hero images are downloaded from each color's Shopify
     * collection page and cached at storage/app/public/color-images/decomex/singles/.
     */
    private const SINGLE_DIR = 'color-images/decomex/singles';

    public function run(): void
    {
        $decomex = Brand::where('name', 'Decomex')->firstOrFail();
        $latex = Material::where('name', 'Latex')->firstOrFail();

        $standard = $this->decomexTexture('Standard (D)', $decomex->id);
        $pastelDeco = $this->decomexTexture('Pastel Deco (D)', $decomex->id);
        $jewelCrystal = $this->decomexTexture('Jewel Crystal (D)', $decomex->id);
        $pearlMetallic = $this->decomexTexture('Pearl/Metallic (D)', $decomex->id);
        $luster = $this->decomexTexture('Luster (D)', $decomex->id);

        $families = ColorFamily::pluck('id', 'name');

        $colors = $this->colorData($standard, $pastelDeco, $jewelCrystal, $pearlMetallic, $luster);

        foreach ($colors as $data) {
            Color::updateOrCreate(
                ['name' => $data['name'], 'brand_id' => $decomex->id],
                [
                    'color_family_id' => $families[$data['family']] ?? null,
                    'brand_id' => $decomex->id,
                    'material_id' => $latex->id,
                    'texture_id' => $data['texture']->id,
                    'color_hex' => $data['hex'],
                    'pms_value' => $data['pms'],
                    'single_image_file_path' => $this->singleImagePath($data['name']),
                    'sort_order' => $data['sort_order'],
                ],
            );
        }
    }

    private function decomexTexture(string $name, string $decomexId): Texture
    {
        return Texture::where('name', $name)->where('brand_id', $decomexId)->firstOrFail();
    }

    /**
     * Resolve the storage-relative path for a color's single-balloon image.
     *
     * Images are pre-downloaded from each color's Shopify collection page and
     * committed under storage/app/public/color-images/decomex/singles/, named
     * "{color name}.{ext}" (e.g. "100 - Standard White.jpg"). The extension
     * varies — most are .jpg, but Luster and a few Standard colors are .png.
     *
     * Returns null if no matching file exists, so the color row is still
     * created and the UI falls back to the hex swatch.
     */
    private function singleImagePath(string $colorName): ?string
    {
        $disk = Storage::disk('public');

        foreach (['jpg', 'png', 'webp'] as $ext) {
            $relative = self::SINGLE_DIR.'/'.$colorName.'.'.$ext;

            if ($disk->exists($relative)) {
                return $relative;
            }
        }

        return null;
    }

    /**
     * Full Decomex latex balloon color list from the official Google Sheets color & size table.
     *
     * Colors are grouped by finish and ordered by their Decomex item number.
     *
     * @return array<int, array{name: string, texture: Texture, family: string, hex: ?string, pms: ?string, sort_order: int}>
     */
    private function colorData(Texture $standard, Texture $pastelDeco, Texture $jewelCrystal, Texture $pearlMetallic, Texture $luster): array
    {
        return [

            // ============================================================
            // STANDARD FINISH (100–199) — 18 colors
            // Opaque, bold colors.
            // ============================================================
            ['name' => '100 - Standard White',        'texture' => $standard, 'family' => 'Whites',     'hex' => '#FBF9FC', 'pms' => null, 'sort_order' => 100],
            ['name' => '110 - Standard Red',          'texture' => $standard, 'family' => 'Reds',       'hex' => '#EE2A2E', 'pms' => null, 'sort_order' => 110],
            ['name' => '111 - Standard Ruby Red',     'texture' => $standard, 'family' => 'Reds',       'hex' => '#A60018', 'pms' => null, 'sort_order' => 111],
            ['name' => '120 - Standard Pink',         'texture' => $standard, 'family' => 'Pinks',      'hex' => '#FE89B3', 'pms' => null, 'sort_order' => 120],
            ['name' => '130 - Standard Orange',       'texture' => $standard, 'family' => 'Oranges',    'hex' => '#EF6B23', 'pms' => null, 'sort_order' => 130],
            ['name' => '140 - Standard Yellow',       'texture' => $standard, 'family' => 'Yellows',    'hex' => '#F6E813', 'pms' => null, 'sort_order' => 140],
            ['name' => '141 - Standard Deep Yellow',  'texture' => $standard, 'family' => 'Yellows',    'hex' => '#D3B01E', 'pms' => null, 'sort_order' => 141],
            ['name' => '150 - Standard Lavender',     'texture' => $standard, 'family' => 'Purples',    'hex' => '#9A509F', 'pms' => null, 'sort_order' => 150],
            ['name' => '151 - Standard Purple',       'texture' => $standard, 'family' => 'Purples',    'hex' => '#802A8B', 'pms' => null, 'sort_order' => 151],
            ['name' => '160 - Standard Green',        'texture' => $standard, 'family' => 'Greens',     'hex' => '#28B45E', 'pms' => null, 'sort_order' => 160],
            ['name' => '161 - Standard Forest Green', 'texture' => $standard, 'family' => 'Greens',     'hex' => '#228B22', 'pms' => null, 'sort_order' => 161],
            ['name' => '170 - Standard Medium Blue',  'texture' => $standard, 'family' => 'Blues',      'hex' => '#1C89E8', 'pms' => null, 'sort_order' => 170],
            ['name' => '171 - Standard Blue',         'texture' => $standard, 'family' => 'Blues',      'hex' => '#0177D7', 'pms' => null, 'sort_order' => 171],
            ['name' => '172 - Standard Carolina Blue', 'texture' => $standard, 'family' => 'Blues',      'hex' => '#49808A', 'pms' => null, 'sort_order' => 172],
            ['name' => '173 - Standard Aqua Blue',    'texture' => $standard, 'family' => 'Blues',      'hex' => '#479A88', 'pms' => null, 'sort_order' => 173],
            ['name' => '174 - Standard Ocean Blue',   'texture' => $standard, 'family' => 'Blues',      'hex' => '#08729A', 'pms' => null, 'sort_order' => 174],
            ['name' => '180 - Standard Black',        'texture' => $standard, 'family' => 'Blacks',     'hex' => '#0C0D0F', 'pms' => null, 'sort_order' => 180],
            ['name' => '199 - Standard Assorted',     'texture' => $standard, 'family' => 'Assortment', 'hex' => null,       'pms' => null, 'sort_order' => 199],
            // Not on the official chart but sold by distributors (BargainBalloons)
            ['name' => '210 - Standard Dynasty Red',   'texture' => $standard, 'family' => 'Reds',       'hex' => null,       'pms' => null, 'sort_order' => 210],

            // ============================================================
            // PASTEL DECO FINISH (200–279) — 62 colors
            // Muted, chalky pastels with a matte, velvety appearance.
            // ============================================================

            // Core pastels (200–217)
            ['name' => '200 - Pastel Deco Dusk White',   'texture' => $pastelDeco, 'family' => 'Whites',   'hex' => '#E7E3D8', 'pms' => null, 'sort_order' => 200],
            ['name' => '201 - Pastel Deco Grey',         'texture' => $pastelDeco, 'family' => 'Blacks',   'hex' => '#D1CEC7', 'pms' => null, 'sort_order' => 201],
            ['name' => '202 - Pastel Deco Sand',         'texture' => $pastelDeco, 'family' => 'Browns',   'hex' => '#E1D8C7', 'pms' => null, 'sort_order' => 202],
            ['name' => '203 - Pastel Deco Desert Sand',  'texture' => $pastelDeco, 'family' => 'Browns',   'hex' => '#A97D56', 'pms' => null, 'sort_order' => 203],
            ['name' => '204 - Pastel Deco Stone',        'texture' => $pastelDeco, 'family' => 'Browns',   'hex' => '#8E8D7B', 'pms' => null, 'sort_order' => 204],
            ['name' => '205 - Pastel Deco Storm',        'texture' => $pastelDeco, 'family' => 'Browns',   'hex' => '#6B9098', 'pms' => null, 'sort_order' => 205],
            ['name' => '206 - Pastel Sage Green',        'texture' => $pastelDeco, 'family' => 'Greens',   'hex' => '#4A725A', 'pms' => null, 'sort_order' => 206],
            ['name' => '207 - Pastel Coral Grey',        'texture' => $pastelDeco, 'family' => 'Pinks',    'hex' => '#978778', 'pms' => null, 'sort_order' => 207],
            ['name' => '208 - Pastel Pebble Grey',       'texture' => $pastelDeco, 'family' => 'Blacks',   'hex' => '#6D665C', 'pms' => null, 'sort_order' => 208],
            ['name' => '209 - Pastel Light Grey',        'texture' => $pastelDeco, 'family' => 'Blacks',   'hex' => '#CFD3D4', 'pms' => null, 'sort_order' => 209],
            ['name' => '211 - Pastel Deco Fuchsia',      'texture' => $pastelDeco, 'family' => 'Pinks',    'hex' => '#EA479A', 'pms' => null, 'sort_order' => 211],
            ['name' => '212 - Pastel Deco Magenta',      'texture' => $pastelDeco, 'family' => 'Pinks',    'hex' => '#B82E6C', 'pms' => null, 'sort_order' => 212],
            ['name' => '213 - Pastel Deco Rose',         'texture' => $pastelDeco, 'family' => 'Pinks',    'hex' => '#EB1F92', 'pms' => null, 'sort_order' => 213],
            ['name' => '214 - Pastel Deco Wild Berry',   'texture' => $pastelDeco, 'family' => 'Purples',  'hex' => '#79384C', 'pms' => null, 'sort_order' => 214],
            ['name' => '215 - Pastel Deco Dusty Rose',   'texture' => $pastelDeco, 'family' => 'Pinks',    'hex' => '#98777E', 'pms' => null, 'sort_order' => 215],
            ['name' => '216 - Pastel Deco Plum',         'texture' => $pastelDeco, 'family' => 'Purples',  'hex' => '#695A6D', 'pms' => null, 'sort_order' => 216],
            ['name' => '217 - Pastel Deco Deep Rose',    'texture' => $pastelDeco, 'family' => 'Pinks',    'hex' => '#934B33', 'pms' => null, 'sort_order' => 217],

            // Extended pastels (218–279)
            ['name' => '218 - Pastel Deco Sahara Rose',     'texture' => $pastelDeco, 'family' => 'Pinks',   'hex' => '#AA4042', 'pms' => null, 'sort_order' => 218],
            ['name' => '220 - Pastel Deco Light Pink',      'texture' => $pastelDeco, 'family' => 'Pinks',   'hex' => '#FFB6C1', 'pms' => null, 'sort_order' => 220],
            ['name' => '221 - Pastel Deco Baby Pink',       'texture' => $pastelDeco, 'family' => 'Pinks',   'hex' => '#F5A1B1', 'pms' => null, 'sort_order' => 221],
            ['name' => '222 - Pastel Deco Taffy Pink',      'texture' => $pastelDeco, 'family' => 'Pinks',   'hex' => '#FCCCDA', 'pms' => null, 'sort_order' => 222],
            ['name' => '223 - Pastel Deco Candy Pink',      'texture' => $pastelDeco, 'family' => 'Pinks',   'hex' => '#B15A84', 'pms' => null, 'sort_order' => 223],
            ['name' => '224 - Pastel Deco Clay Pink',       'texture' => $pastelDeco, 'family' => 'Pinks',   'hex' => '#C56652', 'pms' => null, 'sort_order' => 224],
            ['name' => '225 - Pastel Deco Flamingo Pink',   'texture' => $pastelDeco, 'family' => 'Pinks',   'hex' => '#D984A3', 'pms' => null, 'sort_order' => 225],
            ['name' => '226 - Pastel Deco Princess Pink',   'texture' => $pastelDeco, 'family' => 'Pinks',   'hex' => '#BA2042', 'pms' => null, 'sort_order' => 226],
            ['name' => '230 - Pastel Deco Cider Orange',    'texture' => $pastelDeco, 'family' => 'Oranges', 'hex' => '#9D4223', 'pms' => null, 'sort_order' => 230],
            ['name' => '231 - Pastel Deco Peach',           'texture' => $pastelDeco, 'family' => 'Oranges', 'hex' => '#FAAC57', 'pms' => null, 'sort_order' => 231],
            ['name' => '232 - Pastel Deco Coral',           'texture' => $pastelDeco, 'family' => 'Pinks',   'hex' => '#BC4B3B', 'pms' => null, 'sort_order' => 232],
            ['name' => '233 - Pastel Deco Cameo',           'texture' => $pastelDeco, 'family' => 'Oranges', 'hex' => '#E8CCC8', 'pms' => null, 'sort_order' => 233],
            ['name' => '241 - Pastel Deco Ivory',           'texture' => $pastelDeco, 'family' => 'Yellows', 'hex' => '#F5EB58', 'pms' => null, 'sort_order' => 241],
            ['name' => '242 - Pastel Deco Golden Yellow',   'texture' => $pastelDeco, 'family' => 'Yellows', 'hex' => '#F7B516', 'pms' => null, 'sort_order' => 242],
            ['name' => '244 - Pastel Deco Yellowish',       'texture' => $pastelDeco, 'family' => 'Yellows', 'hex' => '#FCFD97', 'pms' => null, 'sort_order' => 244],
            ['name' => '245 - Pastel Deco Amber',           'texture' => $pastelDeco, 'family' => 'Yellows', 'hex' => '#E2B22A', 'pms' => null, 'sort_order' => 245],
            ['name' => '246 - Pastel Deco Salmon',          'texture' => $pastelDeco, 'family' => 'Pinks',   'hex' => '#C8A891', 'pms' => null, 'sort_order' => 246],
            ['name' => '250 - Pastel Deco Floral',          'texture' => $pastelDeco, 'family' => 'Purples', 'hex' => '#C69EDC', 'pms' => null, 'sort_order' => 250],
            ['name' => '251 - Pastel Deco Floral Blossom',  'texture' => $pastelDeco, 'family' => 'Purples', 'hex' => '#866C93', 'pms' => null, 'sort_order' => 251],
            ['name' => '252 - Pastel Deco Lilac',           'texture' => $pastelDeco, 'family' => 'Purples', 'hex' => '#9269A3', 'pms' => null, 'sort_order' => 252],
            ['name' => '253 - Pastel Deco Burgundy',        'texture' => $pastelDeco, 'family' => 'Purples', 'hex' => '#80011F', 'pms' => null, 'sort_order' => 253],
            ['name' => '254 - Pastel Deco Chocolate Brown', 'texture' => $pastelDeco, 'family' => 'Browns',  'hex' => '#35281F', 'pms' => null, 'sort_order' => 254],
            ['name' => '255 - Pastel Deco Rosewood',        'texture' => $pastelDeco, 'family' => 'Pinks',   'hex' => '#AD7171', 'pms' => null, 'sort_order' => 255],
            ['name' => '256 - Pastel Deco Pink Blush',      'texture' => $pastelDeco, 'family' => 'Pinks',   'hex' => '#FBD5C0', 'pms' => null, 'sort_order' => 256],
            ['name' => '257 - Pastel Deco Mocha',           'texture' => $pastelDeco, 'family' => 'Browns',  'hex' => '#D88A1B', 'pms' => null, 'sort_order' => 257],
            ['name' => '258 - Pastel Deco Blended Brown',   'texture' => $pastelDeco, 'family' => 'Browns',  'hex' => '#C8AE9F', 'pms' => null, 'sort_order' => 258],
            ['name' => '260 - Pastel Deco Bright Lime',     'texture' => $pastelDeco, 'family' => 'Greens',  'hex' => '#B4C111', 'pms' => null, 'sort_order' => 260],
            ['name' => '261 - Pastel Deco Winter Green',    'texture' => $pastelDeco, 'family' => 'Greens',  'hex' => '#779885', 'pms' => null, 'sort_order' => 261],
            ['name' => '262 - Pastel Deco Lime Green',      'texture' => $pastelDeco, 'family' => 'Greens',  'hex' => '#8FC73E', 'pms' => null, 'sort_order' => 262],
            ['name' => '263 - Matte Mint Green',            'texture' => $pastelDeco, 'family' => 'Greens',  'hex' => '#ACDBA7', 'pms' => null, 'sort_order' => 263],
            ['name' => '264 - Pastel Deco Eucalyptus',      'texture' => $pastelDeco, 'family' => 'Greens',  'hex' => '#A3BBA3', 'pms' => null, 'sort_order' => 264],
            ['name' => '265 - Pastel Deco Olive',           'texture' => $pastelDeco, 'family' => 'Greens',  'hex' => '#8A7701', 'pms' => null, 'sort_order' => 265],
            ['name' => '266 - Pastel Deco Mustard',         'texture' => $pastelDeco, 'family' => 'Yellows', 'hex' => '#8C8A25', 'pms' => null, 'sort_order' => 266],
            ['name' => '267 - Pastel Deco Green Tea',       'texture' => $pastelDeco, 'family' => 'Greens',  'hex' => '#B2DDC2', 'pms' => null, 'sort_order' => 267],
            ['name' => '268 - Pastel Deco Pine Green',      'texture' => $pastelDeco, 'family' => 'Greens',  'hex' => '#00C7B2', 'pms' => null, 'sort_order' => 268],
            ['name' => '270 - Pastel Deco Periwinkle',      'texture' => $pastelDeco, 'family' => 'Blues',   'hex' => '#3D4FA5', 'pms' => null, 'sort_order' => 270],
            ['name' => '271 - Pastel Deco Light Blue',      'texture' => $pastelDeco, 'family' => 'Blues',   'hex' => '#89CCFF', 'pms' => null, 'sort_order' => 271],
            ['name' => '272 - Pastel Deco Pastel Navy Blue', 'texture' => $pastelDeco, 'family' => 'Blues',   'hex' => '#26418E', 'pms' => null, 'sort_order' => 272],
            ['name' => '273 - Pastel Deco Midnight Blue',   'texture' => $pastelDeco, 'family' => 'Blues',   'hex' => '#262366', 'pms' => null, 'sort_order' => 273],
            ['name' => '274 - Pastel Deco Turquoise',       'texture' => $pastelDeco, 'family' => 'Blues',   'hex' => '#0D868F', 'pms' => null, 'sort_order' => 274],
            ['name' => '275 - Pastel Deco Baby Blue',       'texture' => $pastelDeco, 'family' => 'Blues',   'hex' => '#96B7FA', 'pms' => null, 'sort_order' => 275],
            ['name' => '276 - Pastel Deco Royal Blue',      'texture' => $pastelDeco, 'family' => 'Blues',   'hex' => '#005EB8', 'pms' => null, 'sort_order' => 276],
            ['name' => '277 - Pastel Deco Sky Blue',        'texture' => $pastelDeco, 'family' => 'Blues',   'hex' => '#C6E8E9', 'pms' => null, 'sort_order' => 277],
            ['name' => '278 - Pastel Deco Tiffany Blue',    'texture' => $pastelDeco, 'family' => 'Blues',   'hex' => '#0ABBB5', 'pms' => null, 'sort_order' => 278],
            ['name' => '279 - Pastel Deco Dusty Blue',      'texture' => $pastelDeco, 'family' => 'Blues',   'hex' => '#327295', 'pms' => null, 'sort_order' => 279],

            // ============================================================
            // JEWEL CRYSTAL FINISH (300–370) — 6 colors
            // Translucent, jewel-toned colors with a glossy finish.
            // ============================================================
            ['name' => '300 - Jewel Crystal Clear',  'texture' => $jewelCrystal, 'family' => 'Clears',   'hex' => '#F9F7FA', 'pms' => null, 'sort_order' => 300],
            ['name' => '311 - Jewel Chilli Red',     'texture' => $jewelCrystal, 'family' => 'Reds',     'hex' => '#E32227', 'pms' => null, 'sort_order' => 311],
            ['name' => '330 - Jewel Orange',         'texture' => $jewelCrystal, 'family' => 'Oranges',  'hex' => '#FF6600', 'pms' => null, 'sort_order' => 330],
            ['name' => '351 - Jewel Violet',         'texture' => $jewelCrystal, 'family' => 'Purples',  'hex' => '#663398', 'pms' => null, 'sort_order' => 351],
            ['name' => '360 - Jewel Dark Green',     'texture' => $jewelCrystal, 'family' => 'Greens',   'hex' => '#076032', 'pms' => null, 'sort_order' => 360],
            ['name' => '370 - Jewel Dark Blue',      'texture' => $jewelCrystal, 'family' => 'Blues',    'hex' => '#234177', 'pms' => null, 'sort_order' => 370],

            // ============================================================
            // PEARL/METALLIC FINISH (400–480) — 36 colors
            // Shimmering pearlescent and metallic finishes.
            // ============================================================
            ['name' => '400 - Pearl Metallic White',          'texture' => $pearlMetallic, 'family' => 'Whites',   'hex' => '#FBF9FC', 'pms' => null, 'sort_order' => 400],
            ['name' => '401 - Pearl Metallic Silver',         'texture' => $pearlMetallic, 'family' => 'Blacks',   'hex' => '#A3A8A2', 'pms' => null, 'sort_order' => 401],
            ['name' => '402 - Pearl Metallic Pearl White',    'texture' => $pearlMetallic, 'family' => 'Whites',   'hex' => '#FBF9FC', 'pms' => null, 'sort_order' => 402],
            ['name' => '403 - Pearl Metallic Champagne',      'texture' => $pearlMetallic, 'family' => 'Yellows',  'hex' => '#E1E89A', 'pms' => null, 'sort_order' => 403],
            ['name' => '410 - Pearl Metallic Red',            'texture' => $pearlMetallic, 'family' => 'Reds',     'hex' => '#CA3433', 'pms' => null, 'sort_order' => 410],
            ['name' => '411 - Pearl Metallic Fuchsia',        'texture' => $pearlMetallic, 'family' => 'Pinks',    'hex' => '#B6233F', 'pms' => null, 'sort_order' => 411],
            ['name' => '412 - Pearl Metallic Magenta',        'texture' => $pearlMetallic, 'family' => 'Pinks',    'hex' => '#A51C46', 'pms' => null, 'sort_order' => 412],
            ['name' => '420 - Pearl Metallic Pink',           'texture' => $pearlMetallic, 'family' => 'Pinks',    'hex' => '#EDA6C6', 'pms' => null, 'sort_order' => 420],
            ['name' => '421 - Pearl Metallic Light Pink',     'texture' => $pearlMetallic, 'family' => 'Pinks',    'hex' => '#EBA4B6', 'pms' => null, 'sort_order' => 421],
            ['name' => '422 - Pearl Metallic Hot Pink',       'texture' => $pearlMetallic, 'family' => 'Pinks',    'hex' => '#FF8DC1', 'pms' => null, 'sort_order' => 422],
            ['name' => '423 - Pearl Metallic Dark Pink',      'texture' => $pearlMetallic, 'family' => 'Pinks',    'hex' => '#974D6E', 'pms' => null, 'sort_order' => 423],
            ['name' => '430 - Pearl Metallic Orange',         'texture' => $pearlMetallic, 'family' => 'Oranges',  'hex' => '#F15723', 'pms' => null, 'sort_order' => 430],
            ['name' => '431 - Pearl Metallic Peach',          'texture' => $pearlMetallic, 'family' => 'Oranges',  'hex' => '#E8B08B', 'pms' => null, 'sort_order' => 431],
            ['name' => '440 - Pearl Metallic Yellow',         'texture' => $pearlMetallic, 'family' => 'Yellows',  'hex' => '#F6E813', 'pms' => null, 'sort_order' => 440],
            ['name' => '441 - Pearl Metallic Ivory',          'texture' => $pearlMetallic, 'family' => 'Yellows',  'hex' => '#F8F1C7', 'pms' => null, 'sort_order' => 441],
            ['name' => '442 - Metallic Gold',                 'texture' => $pearlMetallic, 'family' => 'Yellows',  'hex' => '#C6952C', 'pms' => null, 'sort_order' => 442],
            ['name' => '443 - Pearl Metallic Rose Gold',      'texture' => $pearlMetallic, 'family' => 'Pinks',    'hex' => '#E07628', 'pms' => null, 'sort_order' => 443],
            ['name' => '444 - Pearl Metallic Copper',         'texture' => $pearlMetallic, 'family' => 'Oranges',  'hex' => '#BD5E4C', 'pms' => null, 'sort_order' => 444],
            ['name' => '445 - Pearl Metallic Rose Pink',      'texture' => $pearlMetallic, 'family' => 'Pinks',    'hex' => '#C5796B', 'pms' => null, 'sort_order' => 445],
            ['name' => '450 - Pearl Metallic Lavender',       'texture' => $pearlMetallic, 'family' => 'Purples',  'hex' => '#9C4F97', 'pms' => null, 'sort_order' => 450],
            ['name' => '451 - Pearl Metallic Purple',         'texture' => $pearlMetallic, 'family' => 'Purples',  'hex' => '#6F2469', 'pms' => null, 'sort_order' => 451],
            ['name' => '452 - Pearl Metallic Light Lavender', 'texture' => $pearlMetallic, 'family' => 'Purples',  'hex' => '#9269A3', 'pms' => null, 'sort_order' => 452],
            ['name' => '453 - Metallic Burgundy',             'texture' => $pearlMetallic, 'family' => 'Purples',  'hex' => '#782338', 'pms' => null, 'sort_order' => 453],
            ['name' => '460 - Pearl Metallic Green',          'texture' => $pearlMetallic, 'family' => 'Greens',   'hex' => '#07876E', 'pms' => null, 'sort_order' => 460],
            ['name' => '461 - Pearl Metallic Light Green',    'texture' => $pearlMetallic, 'family' => 'Greens',   'hex' => '#5ABB47', 'pms' => null, 'sort_order' => 461],
            ['name' => '462 - Pearl Metallic Emerald Green',  'texture' => $pearlMetallic, 'family' => 'Greens',   'hex' => '#037F74', 'pms' => null, 'sort_order' => 462],
            ['name' => '463 - Pearl Metallic Pale Green',     'texture' => $pearlMetallic, 'family' => 'Greens',   'hex' => '#8FAF5A', 'pms' => null, 'sort_order' => 463],
            ['name' => '464 - Pearl Metallic Mint Green',     'texture' => $pearlMetallic, 'family' => 'Greens',   'hex' => '#4F8963', 'pms' => null, 'sort_order' => 464],
            ['name' => '470 - Pearl Metallic Blue',           'texture' => $pearlMetallic, 'family' => 'Blues',    'hex' => '#1072BB', 'pms' => null, 'sort_order' => 470],
            ['name' => '471 - Pearl Metallic Naval Blue',     'texture' => $pearlMetallic, 'family' => 'Blues',    'hex' => '#234177', 'pms' => null, 'sort_order' => 471],
            ['name' => '472 - Pearl Metallic Azure',          'texture' => $pearlMetallic, 'family' => 'Blues',    'hex' => '#71A9DA', 'pms' => null, 'sort_order' => 472],
            ['name' => '473 - Pearl Metallic Light Blue',     'texture' => $pearlMetallic, 'family' => 'Blues',    'hex' => '#BED3E8', 'pms' => null, 'sort_order' => 473],
            ['name' => '474 - Pearl Metallic Periwinkle',     'texture' => $pearlMetallic, 'family' => 'Blues',    'hex' => '#9797BB', 'pms' => null, 'sort_order' => 474],
            ['name' => '475 - Pearl Metallic Aqua',           'texture' => $pearlMetallic, 'family' => 'Blues',    'hex' => '#51A58B', 'pms' => null, 'sort_order' => 475],
            ['name' => '476 - Pearl Metallic Midnight Blue',  'texture' => $pearlMetallic, 'family' => 'Blues',    'hex' => '#22404B', 'pms' => null, 'sort_order' => 476],
            ['name' => '480 - Pearl Metallic Black',          'texture' => $pearlMetallic, 'family' => 'Blacks',   'hex' => '#0C0D0F', 'pms' => null, 'sort_order' => 480],

            // ============================================================
            // LUSTER FINISH (501–508) — 6 colors
            // High-shine, chrome-like reflective finish.
            // ============================================================
            ['name' => '501 - Luster Silver',    'texture' => $luster, 'family' => 'Blacks',   'hex' => '#6E6F6C', 'pms' => null, 'sort_order' => 501],
            ['name' => '502 - Luster Gold',      'texture' => $luster, 'family' => 'Yellows',  'hex' => '#645C49', 'pms' => null, 'sort_order' => 502],
            ['name' => '503 - Luster Rose Gold', 'texture' => $luster, 'family' => 'Pinks',    'hex' => '#704D3E', 'pms' => null, 'sort_order' => 503],
            ['name' => '505 - Luster Purple',    'texture' => $luster, 'family' => 'Purples',  'hex' => '#4C3B62', 'pms' => null, 'sort_order' => 505],
            ['name' => '507 - Luster Blue',      'texture' => $luster, 'family' => 'Blues',    'hex' => '#305971', 'pms' => null, 'sort_order' => 507],
            ['name' => '508 - Luster Green',     'texture' => $luster, 'family' => 'Greens',   'hex' => '#37615A', 'pms' => null, 'sort_order' => 508],
            // Not on the official chart but sold by distributors (BargainBalloons)
            ['name' => '510 - Luster Red',       'texture' => $luster, 'family' => 'Reds',     'hex' => null,       'pms' => null, 'sort_order' => 510],
        ];
    }
}
