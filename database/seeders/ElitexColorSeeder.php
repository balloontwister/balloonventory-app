<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Color;
use App\Models\ColorFamily;
use App\Models\Material;
use App\Models\Texture;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ElitexColorSeeder extends Seeder
{
    private const IMAGE_FOLDER = 'color-images/elitex';

    public function run(): void
    {
        $elitex = Brand::where('name', 'Elitex')->firstOrFail();
        $latex = Material::where('name', 'Latex')->firstOrFail();

        $standard = $this->elitexTexture('Standard (E)', $elitex->id);
        $metallicPearl = $this->elitexTexture('Metallic & Pearl (E)', $elitex->id);
        $pastelRainbow = $this->elitexTexture('Pastel Rainbow (E)', $elitex->id);
        $smoothies = $this->elitexTexture('Smoothies (E)', $elitex->id);
        $superGlow = $this->elitexTexture('Super Glow (E)', $elitex->id);
        $confetti = $this->elitexTexture('Confetti (E)', $elitex->id);

        $families = ColorFamily::pluck('id', 'name');

        $colors = $this->colorData($standard, $metallicPearl, $pastelRainbow, $smoothies, $superGlow, $confetti);

        foreach ($colors as $data) {
            $imagePath = $this->fetchImage($data['name'], $data['image'] ?? null);

            Color::updateOrCreate(
                ['name' => $data['name'], 'brand_id' => $elitex->id],
                [
                    'color_family_id' => $families[$data['family']] ?? null,
                    'brand_id' => $elitex->id,
                    'material_id' => $latex->id,
                    'texture_id' => $data['texture']->id,
                    'color_hex' => $data['hex'],
                    'pms_value' => null,
                    'single_image_file_path' => $imagePath,
                    'sort_order' => $data['sort_order'],
                ],
            );
        }
    }

    private function elitexTexture(string $name, string $elitexId): Texture
    {
        return Texture::where('name', $name)->where('brand_id', $elitexId)->firstOrFail();
    }

    private function fetchImage(string $colorName, ?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $extension = pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION) ?: 'png';
        $relativePath = self::IMAGE_FOLDER.'/'.Str::slug($colorName).'.'.$extension;

        if (Storage::disk('public')->exists($relativePath)) {
            return $relativePath;
        }

        try {
            $response = Http::timeout(15)->get($url);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        Storage::disk('public')->put($relativePath, $response->body());

        return $relativePath;
    }

    /**
     * Full Elitex color list across all six texture categories.
     * HEX values extracted from product images via median-cut quantization.
     * No PMS values are published by Elitex.
     *
     * @return array<int, array{name: string, texture: Texture, family: string, hex: ?string, sort_order: int, image: ?string}>
     */
    private function colorData(
        Texture $standard,
        Texture $metallicPearl,
        Texture $pastelRainbow,
        Texture $smoothies,
        Texture $superGlow,
        Texture $confetti,
    ): array {
        return [
            // STANDARD — 25 colors
            ['name' => 'Aqua',           'texture' => $standard, 'family' => 'Blues',   'hex' => '#14ACBF', 'sort_order' => 10,  'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Aqua-WM.png'],
            ['name' => 'Black',          'texture' => $standard, 'family' => 'Blacks',  'hex' => '#0B0D11', 'sort_order' => 20,  'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Black-WM.png'],
            ['name' => 'Crystal Clear',  'texture' => $standard, 'family' => 'Clears',  'hex' => '#E6EDF3', 'sort_order' => 30,  'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Crystal-clear-WM.png'],
            ['name' => 'Dark Brown',     'texture' => $standard, 'family' => 'Browns',  'hex' => '#482C27', 'sort_order' => 40,  'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Dark-brown-WM.png'],
            ['name' => 'Dark Royal Blue', 'texture' => $standard, 'family' => 'Blues',   'hex' => '#026DEB', 'sort_order' => 50,  'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Dark-royal-blue-WM.png'],
            ['name' => 'Emerald Green',  'texture' => $standard, 'family' => 'Greens',  'hex' => '#009059', 'sort_order' => 60,  'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Emerald-green-WM.png'],
            ['name' => 'Gray',           'texture' => $standard, 'family' => 'Blacks',  'hex' => '#C1BDC8', 'sort_order' => 70,  'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Gray-WM.png'],
            ['name' => 'Green',          'texture' => $standard, 'family' => 'Greens',  'hex' => '#009166', 'sort_order' => 80,  'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Green-WM.png'],
            ['name' => 'Ice Blue',       'texture' => $standard, 'family' => 'Blues',   'hex' => '#488BE3', 'sort_order' => 90,  'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Ice-blue-WM.png'],
            ['name' => 'Light Blue',     'texture' => $standard, 'family' => 'Blues',   'hex' => '#62BFE9', 'sort_order' => 100, 'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Light-blue-WM.png'],
            ['name' => 'Light Pink',     'texture' => $standard, 'family' => 'Pinks',   'hex' => '#F0B7D4', 'sort_order' => 110, 'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Light-pink-WM.png'],
            ['name' => 'Lime Green',     'texture' => $standard, 'family' => 'Greens',  'hex' => '#31C348', 'sort_order' => 120, 'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Lime-green-WM.png'],
            ['name' => 'Navy Blue',      'texture' => $standard, 'family' => 'Blues',   'hex' => '#5B74B0', 'sort_order' => 130, 'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Navy-blue-WM.png'],
            ['name' => 'Olive Green',    'texture' => $standard, 'family' => 'Greens',  'hex' => '#6C957D', 'sort_order' => 140, 'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Olive-green-WM.png'],
            ['name' => 'Orange',         'texture' => $standard, 'family' => 'Oranges', 'hex' => '#FE652C', 'sort_order' => 150, 'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Orange-WM.png'],
            ['name' => 'Orchid',         'texture' => $standard, 'family' => 'Purples', 'hex' => '#B081D9', 'sort_order' => 160, 'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Orchid-WM.png'],
            ['name' => 'Pink',           'texture' => $standard, 'family' => 'Pinks',   'hex' => '#F39FBB', 'sort_order' => 170, 'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Pink-WM.png'],
            ['name' => 'Purple',         'texture' => $standard, 'family' => 'Purples', 'hex' => '#9765D1', 'sort_order' => 180, 'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Purple-WM.png'],
            ['name' => 'Red',            'texture' => $standard, 'family' => 'Reds',    'hex' => '#FC3640', 'sort_order' => 190, 'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Red-WM.png'],
            ['name' => 'Red Wine',       'texture' => $standard, 'family' => 'Reds',    'hex' => '#E6344D', 'sort_order' => 200, 'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Red-wine-WM.png'],
            ['name' => 'Ruby Pink',      'texture' => $standard, 'family' => 'Pinks',   'hex' => '#EE5AA3', 'sort_order' => 210, 'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Ruby-pink-WM.png'],
            ['name' => 'Spring Green',   'texture' => $standard, 'family' => 'Greens',  'hex' => '#13BB8E', 'sort_order' => 220, 'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Spring-green-WM.png'],
            ['name' => 'Teak Brown',     'texture' => $standard, 'family' => 'Browns',  'hex' => '#DF7F5D', 'sort_order' => 230, 'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Teak-brown-WM.png'],
            ['name' => 'White',          'texture' => $standard, 'family' => 'Whites',  'hex' => '#F4F4F4', 'sort_order' => 240, 'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/White-WM.png'],
            ['name' => 'Yellow',         'texture' => $standard, 'family' => 'Yellows', 'hex' => '#DDCB4B', 'sort_order' => 250, 'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Yellow-WM.png'],

            // METALLIC & PEARL — 11 colors
            ['name' => 'Black Pearl',              'texture' => $metallicPearl, 'family' => 'Blacks',  'hex' => '#1A1A1E', 'sort_order' => 260, 'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Black-pearl-WM.png'],
            ['name' => 'Emerald Green Pearl',      'texture' => $metallicPearl, 'family' => 'Greens',  'hex' => '#02685E', 'sort_order' => 270, 'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Emerald-green-pearl-WM.png'],
            ['name' => 'Lavender Pearl',           'texture' => $metallicPearl, 'family' => 'Purples', 'hex' => '#9079A9', 'sort_order' => 280, 'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Lavender-pearl-WM.png'],
            ['name' => 'Metallic Gold',            'texture' => $metallicPearl, 'family' => 'Golds',   'hex' => '#D4A333', 'sort_order' => 290, 'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/M-gold-WM.png'],
            ['name' => 'Metallic Light Blue Pearl', 'texture' => $metallicPearl, 'family' => 'Blues',   'hex' => '#519ABC', 'sort_order' => 300, 'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/M-LT-blue-pearl-WM.png'],
            ['name' => 'Metallic Rose Gold',       'texture' => $metallicPearl, 'family' => 'Golds',   'hex' => '#BB717A', 'sort_order' => 310, 'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/M-Rosegold-WM.png'],
            ['name' => 'Metallic Ruby Red',        'texture' => $metallicPearl, 'family' => 'Reds',    'hex' => '#D5313B', 'sort_order' => 320, 'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/M-Ruby-red-WM.png'],
            ['name' => 'Metallic Silver',          'texture' => $metallicPearl, 'family' => 'Silvers', 'hex' => '#9A9B9E', 'sort_order' => 330, 'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/M-silver-WM.png'],
            ['name' => 'Peach Pearl',              'texture' => $metallicPearl, 'family' => 'Oranges', 'hex' => '#B78A74', 'sort_order' => 340, 'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Peach-pearl-WM.png'],
            ['name' => 'Pink Pearl',               'texture' => $metallicPearl, 'family' => 'Pinks',   'hex' => '#BD99B4', 'sort_order' => 350, 'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Pink-pearl-WM.png'],
            ['name' => 'White Pearl',              'texture' => $metallicPearl, 'family' => 'Whites',  'hex' => '#F0F0F5', 'sort_order' => 360, 'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/White-pearl-WM.png'],

            // PASTEL RAINBOW — 9 colors
            ['name' => 'Baby Blue',      'texture' => $pastelRainbow, 'family' => 'Blues',   'hex' => '#88BBDD', 'sort_order' => 370, 'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Babyblue-WM-1.png'],
            ['name' => 'Beige',          'texture' => $pastelRainbow, 'family' => 'Browns',  'hex' => '#E2B9A9', 'sort_order' => 380, 'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Beige-WM.png'],
            ['name' => 'Cerise',         'texture' => $pastelRainbow, 'family' => 'Pinks',   'hex' => '#DD547E', 'sort_order' => 390, 'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Cerise-WM.png'],
            ['name' => 'Cherry Blossom', 'texture' => $pastelRainbow, 'family' => 'Pinks',   'hex' => '#F299D2', 'sort_order' => 400, 'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Cherryblossom-WM.png'],
            ['name' => 'Coral',          'texture' => $pastelRainbow, 'family' => 'Pinks',   'hex' => '#FD7976', 'sort_order' => 410, 'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Coral-WM.png'],
            ['name' => 'Ivory',          'texture' => $pastelRainbow, 'family' => 'Whites',  'hex' => '#DECF83', 'sort_order' => 420, 'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Ivory-WM.png'],
            ['name' => 'Lemon Green',    'texture' => $pastelRainbow, 'family' => 'Greens',  'hex' => '#9FAD6E', 'sort_order' => 430, 'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Lemon-green-WM.png'],
            ['name' => 'Mint',           'texture' => $pastelRainbow, 'family' => 'Greens',  'hex' => '#85CFC3', 'sort_order' => 440, 'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Mint-WM-1.png'],
            ['name' => 'Spring Lilac',   'texture' => $pastelRainbow, 'family' => 'Purples', 'hex' => '#BD94D3', 'sort_order' => 450, 'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Spring-lilac-WM-1.png'],

            // SMOOTHIES — 6 colors
            ['name' => 'Blue Hawaii', 'texture' => $smoothies, 'family' => 'Blues',   'hex' => '#91B3E6', 'sort_order' => 460, 'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Blue-hawai-WM.png'],
            ['name' => 'Blueberry',   'texture' => $smoothies, 'family' => 'Purples', 'hex' => '#CAABEA', 'sort_order' => 470, 'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Blueberry-WM.png'],
            ['name' => 'Giwi',        'texture' => $smoothies, 'family' => 'Greens',  'hex' => '#89CDCC', 'sort_order' => 480, 'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Giwi-WM-1.png'],
            ['name' => 'Mango',       'texture' => $smoothies, 'family' => 'Oranges', 'hex' => '#DED4B2', 'sort_order' => 490, 'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Mango-WM.png'],
            ['name' => 'Pamelo',      'texture' => $smoothies, 'family' => 'Oranges', 'hex' => '#F4B99F', 'sort_order' => 500, 'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Pamelo-WM.png'],
            ['name' => 'Strawberry',  'texture' => $smoothies, 'family' => 'Pinks',   'hex' => '#D3919C', 'sort_order' => 510, 'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Strawberry-WM.png'],

            // SUPER GLOW — 6 colors
            ['name' => 'Gold Superglow',       'texture' => $superGlow, 'family' => 'Golds',   'hex' => '#866E53', 'sort_order' => 520, 'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Gold-Superglow-WM.png'],
            ['name' => 'Green Superglow',      'texture' => $superGlow, 'family' => 'Greens',  'hex' => '#04595E', 'sort_order' => 530, 'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Green-Superglow-WM-1.png'],
            ['name' => 'Light Blue Superglow', 'texture' => $superGlow, 'family' => 'Blues',   'hex' => '#054470', 'sort_order' => 540, 'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Lightblue-Superglow-WM.png'],
            ['name' => 'Pink Superglow',       'texture' => $superGlow, 'family' => 'Pinks',   'hex' => '#77405F', 'sort_order' => 550, 'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Pink-Superglow-WM.png'],
            ['name' => 'Purple Superglow',     'texture' => $superGlow, 'family' => 'Purples', 'hex' => '#3A285D', 'sort_order' => 560, 'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Purple-Superglow-WM.png'],
            ['name' => 'Silver Superglow',     'texture' => $superGlow, 'family' => 'Silvers', 'hex' => '#586476', 'sort_order' => 570, 'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Silver-Superglow-WM.png'],

            // CONFETTI — 7 colors (HEX values are muted due to clear balloon body in photos;
            // family assignments are by name, not hex.)
            ['name' => 'Confetti Blue',      'texture' => $confetti, 'family' => 'Blues',   'hex' => '#4E7F9F', 'sort_order' => 580, 'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Confetti-blue-WM-Medium-1.png'],
            ['name' => 'Confetti Gold',      'texture' => $confetti, 'family' => 'Golds',   'hex' => '#A0B1C1', 'sort_order' => 590, 'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Confetti-gold-WM-Medium-1.png'],
            ['name' => 'Confetti Green',     'texture' => $confetti, 'family' => 'Greens',  'hex' => '#93ADBD', 'sort_order' => 600, 'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Confetti-Green-WM-Medium-1.png'],
            ['name' => 'Confetti Purple',    'texture' => $confetti, 'family' => 'Purples', 'hex' => '#6B728B', 'sort_order' => 610, 'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Confetti-purple-WM-Medium-1.png'],
            ['name' => 'Confetti Red',       'texture' => $confetti, 'family' => 'Reds',    'hex' => '#95A2BC', 'sort_order' => 620, 'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Confetti-red-WM-Medium-1.png'],
            ['name' => 'Confetti Rose Gold', 'texture' => $confetti, 'family' => 'Golds',   'hex' => '#97A0B2', 'sort_order' => 630, 'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Confetti-rose-gold-WM-Medium-1.png'],
            ['name' => 'Confetti Silver',    'texture' => $confetti, 'family' => 'Silvers', 'hex' => '#AEC1D7', 'sort_order' => 640, 'image' => 'https://elitexballoonsusa.com/wp-content/uploads/2024/02/Confetti-silver-WM-Medium-1.png'],
        ];
    }
}
