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

class KalisanColorSeeder extends Seeder
{
    /**
     * Folder under storage/app/public/ for downloaded swatch images.
     * Production reaches Kalisan's CDN directly when this seeder runs there,
     * so no SCP is needed — the file is fetched once and cached on disk.
     */
    private const IMAGE_FOLDER = 'color-images/kalisan';

    public function run(): void
    {
        $kalisan = Brand::where('name', 'Kalisan')->firstOrFail();
        $latex = Material::where('name', 'Latex')->firstOrFail();

        $standard = $this->kalisanTexture('Standard (K)', $kalisan->id);
        $retro = $this->kalisanTexture('Retro (K)', $kalisan->id);
        $macaron = $this->kalisanTexture('Macaron (K)', $kalisan->id);
        $opaqueSatin = $this->kalisanTexture('Opaque Satin (K)', $kalisan->id);
        $metallic = $this->kalisanTexture('Metallic (K)', $kalisan->id);
        $pearl = $this->kalisanTexture('Pearl (K)', $kalisan->id);
        $crystal = $this->kalisanTexture('Crystal (K)', $kalisan->id);
        $mirror = $this->kalisanTexture('Mirror (K)', $kalisan->id);
        $aura = $this->kalisanTexture('Aura (K)', $kalisan->id);

        $families = ColorFamily::pluck('id', 'name');

        $colors = $this->colorData($standard, $retro, $macaron, $opaqueSatin, $metallic, $pearl, $crystal, $mirror, $aura);

        foreach ($colors as $data) {
            $imagePath = $this->fetchImage($data['name'], $data['image'] ?? null);

            Color::updateOrCreate(
                ['name' => $data['name'], 'brand_id' => $kalisan->id],
                [
                    'color_family_id' => $families[$data['family']] ?? null,
                    'brand_id' => $kalisan->id,
                    'material_id' => $latex->id,
                    'texture_id' => $data['texture']->id,
                    'color_hex' => $data['hex'],
                    'pms_value' => $data['pms'],
                    'single_image_file_path' => $imagePath,
                    'sort_order' => $data['sort_order'],
                ],
            );
        }
    }

    private function kalisanTexture(string $name, string $kalisanId): Texture
    {
        return Texture::where('name', $name)->where('brand_id', $kalisanId)->firstOrFail();
    }

    /**
     * Download a swatch image to the public disk and return its relative path.
     * Returns null for any failure (404, network, missing url) so the color
     * row can still be created — the swatch falls back to the hex value.
     */
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
     * Full Kalisan color list combining the brand's published PDF
     * (intake/kalisan/KALISAN COLORS PANTONE CODES.pdf) with the by-color catalog at
     * https://en.kalisan.com.tr/plain-latex-balloons-by-color/.
     *
     * PMS values are pulled directly from the PDF; hex codes are converted
     * from the PMS or estimated from the published swatch when no PMS exists.
     *
     * @return array<int, array{name: string, texture: Texture, family: string, hex: ?string, pms: ?string, sort_order: int, image: ?string}>
     */
    private function colorData(
        Texture $standard,
        Texture $retro,
        Texture $macaron,
        Texture $opaqueSatin,
        Texture $metallic,
        Texture $pearl,
        Texture $crystal,
        Texture $mirror,
        Texture $aura,
    ): array {
        return [
            // STANDARD — opaque pastels. PMS from PDF where listed.
            ['name' => 'White',           'texture' => $standard, 'family' => 'Whites',  'hex' => '#FFFFFF', 'pms' => null,                  'sort_order' => 10,  'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/01/2312-Standard-White-12-inch-w-logo-300x300.png'],
            ['name' => 'Red',             'texture' => $standard, 'family' => 'Reds',    'hex' => '#C5302A', 'pms' => 'PMS 2035 CP',         'sort_order' => 20,  'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/05/2313-Standard-Red-12-18-inch-w-logo-300x300.png'],
            ['name' => 'Blue',            'texture' => $standard, 'family' => 'Blues',   'hex' => '#006FB3', 'pms' => 'PMS 6123 CP',         'sort_order' => 30,  'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/01/2314-Standard-Blue-12-inch-w-logo-300x300.png'],
            ['name' => 'Yellow',          'texture' => $standard, 'family' => 'Yellows', 'hex' => '#F9DD16', 'pms' => 'PMS 106 CP',          'sort_order' => 40,  'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/05/2315-Standard-Yellow-12-18-inch-w-logo-300x300.png'],
            ['name' => 'Green',           'texture' => $standard, 'family' => 'Greens',  'hex' => '#26D07C', 'pms' => 'PMS 7481 C',          'sort_order' => 50,  'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/01/2316-Standard-Green-12-inch-w-logo-300x300.png'],
            ['name' => 'Lilac',           'texture' => $standard, 'family' => 'Purples', 'hex' => '#C8B3D8', 'pms' => 'PMS 2071 U',          'sort_order' => 60,  'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/05/2317-Standard-Lilac-12-18-inch-w-logo-300x300.png'],
            ['name' => 'Turquoise',       'texture' => $standard, 'family' => 'Blues',   'hex' => '#009CA6', 'pms' => 'PMS 3125 C',          'sort_order' => 70,  'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/05/2318-Standard-Turquoise-12-18-inch-w-logo-300x300.png'],
            ['name' => 'Dark Blue',       'texture' => $standard, 'family' => 'Blues',   'hex' => '#003B71', 'pms' => 'PMS 2965 C',          'sort_order' => 80,  'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/01/2319-Standard-Dark-Blue-12-inch-w-logo-300x300.png'],
            ['name' => 'Orange',          'texture' => $standard, 'family' => 'Oranges', 'hex' => '#FF8038', 'pms' => 'PMS 2018 XGC',        'sort_order' => 90,  'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/01/2320-Standard-Orange-12-inch-w-logo-300x300.png'],
            ['name' => 'Fuchsia',         'texture' => $standard, 'family' => 'Pinks',   'hex' => '#FE5BAC', 'pms' => 'PMS 812 C',           'sort_order' => 100, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/05/2321-Standard-Fuchsia-12-18-inch-w-logo-300x300.png'],
            ['name' => 'Amber',           'texture' => $standard, 'family' => 'Yellows', 'hex' => '#FFC600', 'pms' => 'PMS 7548 C',          'sort_order' => 110, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/05/2322-Standard-Amber-12-18-inch-w-logo-300x300.png'],
            ['name' => 'Violet',          'texture' => $standard, 'family' => 'Purples', 'hex' => '#6B5BC8', 'pms' => 'PMS 2091 U',          'sort_order' => 120, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/01/2323-Standard-Violet-12-inch-w-logo-300x300.png'],
            ['name' => 'Lime Green',      'texture' => $standard, 'family' => 'Greens',  'hex' => '#BCEA5E', 'pms' => 'PMS 2298 C',          'sort_order' => 130, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/05/2324-Standard-Lime-Green-12-18-inch-w-logo-300x300.png'],
            ['name' => 'Light Pink',      'texture' => $standard, 'family' => 'Pinks',   'hex' => '#F8C9D4', 'pms' => 'PMS 705 C',           'sort_order' => 140, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/01/2325-Standard-Light-Pink-12-inch-w-logo-300x300.png'],
            ['name' => 'Baby Blue',       'texture' => $standard, 'family' => 'Blues',   'hex' => '#92CDE8', 'pms' => 'PMS 2905 CP',         'sort_order' => 150, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/05/2328-Standard-Baby-Blue-12-18-inch-w-logo-300x300.png'],
            ['name' => 'Dark Green',      'texture' => $standard, 'family' => 'Greens',  'hex' => '#1C5F3D', 'pms' => 'PMS 554 CP',          'sort_order' => 160, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/05/2329-Standard-Dark-Green-12-18-inch-w-logo-300x300.png'],
            ['name' => 'Sea Green',       'texture' => $standard, 'family' => 'Greens',  'hex' => '#97E0CB', 'pms' => 'PMS 332 C',           'sort_order' => 170, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/01/2330-Standard-Sea-Green-12-inch-w-logo-300x300.png'],
            ['name' => 'Black',           'texture' => $standard, 'family' => 'Blacks',  'hex' => '#231F20', 'pms' => null,                  'sort_order' => 180, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/01/2332-Standard-Black-12-inch-w-logo-300x300.png'],
            ['name' => 'Baby Pink',       'texture' => $standard, 'family' => 'Pinks',   'hex' => '#FFB9BB', 'pms' => 'PMS 2332 C',          'sort_order' => 190, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/01/2334-Standard-Baby-Pink-12-inch-w-logo-300x300.png'],
            ['name' => 'Grey',            'texture' => $standard, 'family' => 'Blacks',  'hex' => '#BDBDBD', 'pms' => 'PMS Cool Gray 4 C',   'sort_order' => 200, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/05/2335-Standard-Grey-12-18-inch-w-logo-300x300.png'],
            ['name' => 'Mint Green',      'texture' => $standard, 'family' => 'Greens',  'hex' => '#99E2B4', 'pms' => 'PMS 351 C',           'sort_order' => 210, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/05/2336-Standard-Mint-Green-12-18-inch-w-logo-300x300.png'],
            ['name' => 'Candy Pink',      'texture' => $standard, 'family' => 'Pinks',   'hex' => '#F8C0CC', 'pms' => 'PMS 2036 C',          'sort_order' => 220, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/01/2337-Standard-Candy-Pink-12-inch-w-logo-300x300.png'],
            ['name' => 'Blush',           'texture' => $standard, 'family' => 'Whites',  'hex' => '#F6E7D7', 'pms' => 'PMS 9180 U',          'sort_order' => 230, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/01/2339-Standard-Blush-12-inch-w-logo-300x300.png'],
            ['name' => 'Burgundy',        'texture' => $standard, 'family' => 'Reds',    'hex' => '#6F1F30', 'pms' => 'PMS 4077 C',          'sort_order' => 240, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/05/2340-Standard-Burgundy-12-18-inch-w-logo-300x300.png'],
            ['name' => 'Coral',           'texture' => $standard, 'family' => 'Pinks',   'hex' => '#FF8369', 'pms' => 'PMS 170 C',           'sort_order' => 250, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/05/2341-Standard-Coral-12-18-inch-w-logo-300x300.png'],
            ['name' => 'Navy',            'texture' => $standard, 'family' => 'Blues',   'hex' => '#1E2E4F', 'pms' => 'PMS 2767 CP',         'sort_order' => 260, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/05/2342-Standard-Navy-12-18-inch-w-logo-300x300.png'],
            ['name' => 'Flamingo Pink',   'texture' => $standard, 'family' => 'Pinks',   'hex' => '#F5A7C4', 'pms' => 'PMS Red 0331 U',      'sort_order' => 270, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/01/2344-Standard-Flamingo-Pink-12-inch-w-logo-300x300.png'],
            ['name' => 'Chocolate Brown', 'texture' => $standard, 'family' => 'Browns',  'hex' => '#5C3A28', 'pms' => 'PMS 7596 CP',         'sort_order' => 280, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/05/2345-Standard-Chocolate-Brown-12-18-inch-w-logo-300x300.png'],
            ['name' => 'Caramel Brown',   'texture' => $standard, 'family' => 'Browns',  'hex' => '#BB8650', 'pms' => 'PMS 729 CP',          'sort_order' => 290, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/05/2346-Standard-Caramel-Brown-12-18-inch-w-logo-300x300.png'],
            ['name' => 'Caribbean Blue',  'texture' => $standard, 'family' => 'Blues',   'hex' => '#00ACC8', 'pms' => null,                  'sort_order' => 300, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2024/02/2347-Standard-Caribbean-Blue-12-inch-w-logo-300x300.png'],
            ['name' => 'Pink Blush',      'texture' => $standard, 'family' => 'Pinks',   'hex' => '#F4DEDA', 'pms' => 'PMS 9241 C',          'sort_order' => 310, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/01/2348-Standard-Pink-Blush-12-inch-w-logo-300x300.png'],
            ['name' => 'Hazelnut',        'texture' => $standard, 'family' => 'Browns',  'hex' => '#C8A87B', 'pms' => 'PMS 482 CP',          'sort_order' => 320, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/05/2349-Standard-Hazelnut-12-inch-w-logo-300x300.png'],
            ['name' => 'Peach',           'texture' => $standard, 'family' => 'Oranges', 'hex' => '#FFCC8F', 'pms' => 'PMS 2008 U',          'sort_order' => 330, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/05/2350-Standard-Peach-12-18-inch-w-logo-1-300x300.png'],
            ['name' => 'Clay Pink',       'texture' => $standard, 'family' => 'Pinks',   'hex' => '#D08585', 'pms' => null,                  'sort_order' => 340, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/07/2351-Standard-Clay-Pink-12-inch-w-logo-1-300x300.png'],
            ['name' => 'Deep Red',        'texture' => $standard, 'family' => 'Reds',    'hex' => '#A4173A', 'pms' => null,                  'sort_order' => 350, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/07/2352-Standard-Deep-Red-12-inch-w-logo-1-300x300.png'],
            ['name' => 'Plum',            'texture' => $standard, 'family' => 'Purples', 'hex' => '#7A3D62', 'pms' => null,                  'sort_order' => 360, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/07/2353-Standard-Plum-12-inch-w-logo-1-300x300.png'],
            ['name' => 'Queen Pink',      'texture' => $standard, 'family' => 'Pinks',   'hex' => '#E6789C', 'pms' => null,                  'sort_order' => 370, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2024/02/2354-Standard-Queen-Pink-12-inch-w-logo-300x300.png'],
            ['name' => 'Periwinkle',      'texture' => $standard, 'family' => 'Blues',   'hex' => '#7C8BC4', 'pms' => null,                  'sort_order' => 380, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2025/02/2355-Standard-Periwinkle-12-inch-w-logo-300x300.png'],
            ['name' => 'Transparent',     'texture' => $standard, 'family' => 'Clears',  'hex' => null,      'pms' => null,                  'sort_order' => 390, 'image' => null],

            // RETRO — muted vintage palette. PMS from PDF where listed.
            ['name' => 'Retro Rust Orange',   'texture' => $retro, 'family' => 'Oranges', 'hex' => '#C25B3E', 'pms' => 'PMS 7583 CP',       'sort_order' => 400, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/05/8001-Retro-Rust-Orange-12-18-inch-w-logo-300x300.png'],
            ['name' => 'Retro Mustard',       'texture' => $retro, 'family' => 'Yellows', 'hex' => '#DAB429', 'pms' => 'PMS 6005 CP',       'sort_order' => 410, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2021/07/8002-Retro-Mustard-12-inch-300x300.png'],
            ['name' => 'Retro Deep Blue',     'texture' => $retro, 'family' => 'Blues',   'hex' => '#00A0B0', 'pms' => 'PMS 7710 C',        'sort_order' => 420, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/05/8003-Retro-Deep-Blue-12-18-inch-w-logo-300x300.png'],
            ['name' => 'Retro Blue Glass',    'texture' => $retro, 'family' => 'Blues',   'hex' => '#ACDDDE', 'pms' => 'PMS 629 C',         'sort_order' => 430, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2021/07/8004-Retro-Blue-Glass-12-inch-w-logo-300x300.png'],
            ['name' => 'Retro Storm',         'texture' => $retro, 'family' => 'Blues',   'hex' => '#C2D5DA', 'pms' => 'PMS 552 U',         'sort_order' => 440, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/05/8005-Retro-Storm-12-18-inch-w-logo-300x300.png'],
            ['name' => 'Retro Sage',          'texture' => $retro, 'family' => 'Greens',  'hex' => '#ABC8AC', 'pms' => 'PMS 557 U',         'sort_order' => 450, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/05/8006-Retro-Sage-12-18-inch-w-logo-300x300.png'],
            ['name' => 'Retro Winter Green',  'texture' => $retro, 'family' => 'Greens',  'hex' => '#B7E3D8', 'pms' => 'PMS 572 U',         'sort_order' => 460, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2021/07/8007-Retro-Winter-Green-12-inch-w-logo-300x300.png'],
            ['name' => 'Retro Eucalyptus',    'texture' => $retro, 'family' => 'Greens',  'hex' => '#92A47C', 'pms' => 'PMS 4179 C',        'sort_order' => 470, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/05/8008-Retro-Eucalyptus-12-18-inch-w-logo-300x300.png'],
            ['name' => 'Retro Olive',         'texture' => $retro, 'family' => 'Greens',  'hex' => '#C0C277', 'pms' => 'PMS 4232 U',        'sort_order' => 480, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/05/8009-Retro-Olive-12-18-inch-w-logo-300x300.png'],
            ['name' => 'Retro Stone',         'texture' => $retro, 'family' => 'Browns',  'hex' => '#C8C2B7', 'pms' => 'PMS 400 U',         'sort_order' => 490, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2021/07/8010-Retro-Stone-12-inch-w-logo-300x300.png'],
            ['name' => 'Retro Lavender',      'texture' => $retro, 'family' => 'Purples', 'hex' => '#C4ABD8', 'pms' => 'PMS 2080 U',        'sort_order' => 500, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/05/8011-Retro-Lavender-12-18-inch-w-logo-300x300.png'],
            ['name' => 'Retro Wild Berry',    'texture' => $retro, 'family' => 'Purples', 'hex' => '#B1357E', 'pms' => 'PMS 676 U',         'sort_order' => 510, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/05/8012-Retro-Wild-Berry-12-18-inch-w-logo-300x300.png'],
            ['name' => 'Retro Dusty Rose',    'texture' => $retro, 'family' => 'Pinks',   'hex' => '#DFB2C5', 'pms' => 'PMS 684 U',         'sort_order' => 520, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/05/8013-Retro-Dusty-Rose-12-18-inch-w-logo-300x300.png'],
            ['name' => 'Retro Desert Sand',   'texture' => $retro, 'family' => 'Browns',  'hex' => '#C9B190', 'pms' => 'PMS 7528 C',        'sort_order' => 530, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2021/07/8014-Retro-Desert-Sand-12-inch-w-logo-300x300.png'],
            ['name' => 'Retro White Sand',    'texture' => $retro, 'family' => 'Whites',  'hex' => '#EDE5C8', 'pms' => 'PMS 9064 C',        'sort_order' => 540, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2021/07/8015-Retro-White-Sand-12-inch-w-logo-300x300.png'],
            ['name' => 'Retro Smoke',         'texture' => $retro, 'family' => 'Whites',  'hex' => '#D9D9D6', 'pms' => 'PMS Cool Gray 1 C', 'sort_order' => 550, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2021/07/8016-Retro-Smoke-12-inch-w-logo-300x300.png'],
            ['name' => 'Retro Rosewood',      'texture' => $retro, 'family' => 'Pinks',   'hex' => '#C76C7D', 'pms' => 'PMS 2340 U',        'sort_order' => 560, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/01/8017-Retro-Rosewood-12-inch-w-logo-300x300.png'],
            ['name' => 'Retro White',         'texture' => $retro, 'family' => 'Whites',  'hex' => '#F7F1E1', 'pms' => 'PMS P 1-1 U',       'sort_order' => 570, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/05/8018-Retro-White-12_18-inch-w-logo-300x300.png'],
            ['name' => 'Retro Denim',         'texture' => $retro, 'family' => 'Blues',   'hex' => '#6F8AAA', 'pms' => null,                'sort_order' => 580, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2025/02/8019-Retro-Denim-12-inch-w-logo-300x300.png'],

            // MACARON PASTEL — soft chalky pastels. PMS from PDF for the first wave; later Pale series added in 2024.
            ['name' => 'Macaron Blue',        'texture' => $macaron, 'family' => 'Blues',   'hex' => '#B7D7E3', 'pms' => 'PMS 290 U',         'sort_order' => 600, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/01/3001-Macaron-Blue-12-inch-w-logo-300x300.png'],
            ['name' => 'Macaron Pink',        'texture' => $macaron, 'family' => 'Pinks',   'hex' => '#F4C2C2', 'pms' => 'PMS 12-1813 TCX',   'sort_order' => 610, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/01/3002-Macaron-Pink-12-inch-w-logo-300x300.png'],
            ['name' => 'Macaron Lilac',       'texture' => $macaron, 'family' => 'Purples', 'hex' => '#D4C7E0', 'pms' => 'PMS 9242 C',        'sort_order' => 620, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/01/3003-Macaron-Lilac-12-inch-w-logo-300x300.png'],
            ['name' => 'Macaron Green',       'texture' => $macaron, 'family' => 'Greens',  'hex' => '#79D198', 'pms' => 'PMS 2533 C',        'sort_order' => 630, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/01/3004-Macaron-Green-12-inch-w-logo-300x300.png'],
            ['name' => 'Macaron Yellow',      'texture' => $macaron, 'family' => 'Yellows', 'hex' => '#FBE89C', 'pms' => 'PMS 9123 C',        'sort_order' => 640, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/01/3005-Macaron-Yellow-12-inch-w-logo-300x300.png'],
            ['name' => 'Macaron Salmon',      'texture' => $macaron, 'family' => 'Oranges', 'hex' => '#FAD2BA', 'pms' => 'PMS 9224 U',        'sort_order' => 650, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/01/3006-Macaron-Salmon-12-inch-w-logo-300x300.png'],
            ['name' => 'Macaron Baby Blue',   'texture' => $macaron, 'family' => 'Blues',   'hex' => '#B2DBE3', 'pms' => 'PMS 6748 U',        'sort_order' => 660, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/01/3007-Macaron-Baby-Blue-12-inch-w-logo-300x300.png'],
            ['name' => 'Macaron Pale Yellow', 'texture' => $macaron, 'family' => 'Yellows', 'hex' => '#FCF0BD', 'pms' => null,                'sort_order' => 670, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2024/02/3008-Macaron-Pale-Yellow-12-inch-w-logo-300x300.png'],
            ['name' => 'Macaron Pale Green',  'texture' => $macaron, 'family' => 'Greens',  'hex' => '#C9E7BE', 'pms' => null,                'sort_order' => 680, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2024/02/3009-Macaron-Pale-Green-12-inch-w-logo-300x300.png'],
            ['name' => 'Macaron Pale Pink',   'texture' => $macaron, 'family' => 'Pinks',   'hex' => '#FBDFDF', 'pms' => null,                'sort_order' => 690, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2024/02/3010-Macaron-Pale-Pink-12-inch-w-logo-300x300.png'],
            ['name' => 'Macaron Pale Lilac',  'texture' => $macaron, 'family' => 'Purples', 'hex' => '#E4DCEA', 'pms' => null,                'sort_order' => 700, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2024/02/3011-Macaron-Pale-Lilac-12-inch-w-logo-300x300.png'],
            ['name' => 'Macaron Pale Salmon', 'texture' => $macaron, 'family' => 'Oranges', 'hex' => '#FCE3D2', 'pms' => null,                'sort_order' => 710, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2024/02/3012-Macaron-Pale-Salmon-12-inch-w-logo-300x300.png'],
            ['name' => 'Macaron Pistachio',   'texture' => $macaron, 'family' => 'Greens',  'hex' => '#B9D6A9', 'pms' => null,                'sort_order' => 720, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2025/02/3013-Macaron-Pistachio-12-inch-w-logo-300x300.png'],

            // MIRROR — Kalisan's chrome-finish line.
            ['name' => 'Mirror Gold',        'texture' => $mirror, 'family' => 'Golds',   'hex' => '#C8A951', 'pms' => null, 'sort_order' => 740, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2021/01/5001-Mirror-Gold-12-inch-w-logo-300x300.png'],
            ['name' => 'Mirror Silver',      'texture' => $mirror, 'family' => 'Silvers', 'hex' => '#B0B2B5', 'pms' => null, 'sort_order' => 750, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2021/01/5002-Mirror-Silver-12-inch-w-logo-300x300.png'],
            ['name' => 'Mirror Pink',        'texture' => $mirror, 'family' => 'Pinks',   'hex' => '#E07A9C', 'pms' => null, 'sort_order' => 760, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/05/5003-Mirror-Pink-12-18-inch-w-logo-300x300.png'],
            ['name' => 'Mirror Violet',      'texture' => $mirror, 'family' => 'Purples', 'hex' => '#6A4D8C', 'pms' => null, 'sort_order' => 770, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2021/01/5004-Mirror-Violet-12-inch-w-logo-300x300.png'],
            ['name' => 'Mirror Blue',        'texture' => $mirror, 'family' => 'Blues',   'hex' => '#1E5BA0', 'pms' => null, 'sort_order' => 780, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/05/5005-Mirror-Blue-12-18-inch-w-logo-300x300.png'],
            ['name' => 'Mirror Green',       'texture' => $mirror, 'family' => 'Greens',  'hex' => '#4D8A60', 'pms' => null, 'sort_order' => 790, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2021/01/5006-Mirror-Green-12-inch-w-logo-300x300.png'],
            ['name' => 'Mirror Rose Gold',   'texture' => $mirror, 'family' => 'Pinks',   'hex' => '#D49080', 'pms' => null, 'sort_order' => 800, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2021/01/5007-Mirror-Rose-Gold-12-inch-w-logo-300x300.png'],
            ['name' => 'Mirror Copper',      'texture' => $mirror, 'family' => 'Oranges', 'hex' => '#B87333', 'pms' => null, 'sort_order' => 810, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/05/5008-Mirror-Copper-12-18-inch-w-logo-300x300.png'],
            ['name' => 'Mirror Space Grey',  'texture' => $mirror, 'family' => 'Blacks',  'hex' => '#4F5358', 'pms' => null, 'sort_order' => 820, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2021/01/5009-Mirror-Space-Grey-12-inch-300x300.png'],
            ['name' => 'Mirror Red',         'texture' => $mirror, 'family' => 'Reds',    'hex' => '#B22030', 'pms' => null, 'sort_order' => 830, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/05/5010-Mirror-Red-12-18-inch-w-logo-300x300.png'],
            ['name' => 'Mirror White Gold',  'texture' => $mirror, 'family' => 'Whites',  'hex' => '#EDE3CF', 'pms' => null, 'sort_order' => 840, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2021/11/5011-Mirror-White-Gold-12-inch-w-logo-300x300.png'],
            ['name' => 'Mirror Green Gold',  'texture' => $mirror, 'family' => 'Golds',   'hex' => '#A89D4A', 'pms' => null, 'sort_order' => 850, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/01/5012-Mirror-Green-Gold-12_18-inch-w-logo1-300x300.png'],
            ['name' => 'Mirror Pink Gold',   'texture' => $mirror, 'family' => 'Golds',   'hex' => '#D9A789', 'pms' => null, 'sort_order' => 860, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/01/5013-Mirror-Pink-Gold-12_18-inch1-300x300.png'],
            ['name' => 'Mirror Chocolate',   'texture' => $mirror, 'family' => 'Browns',  'hex' => '#5A3625', 'pms' => null, 'sort_order' => 870, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/05/5014-Mirror-Chocolate-12_18-inch-w-logo-300x300.png'],
            ['name' => 'Mirror Navy',        'texture' => $mirror, 'family' => 'Blues',   'hex' => '#1A2A4A', 'pms' => null, 'sort_order' => 880, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/05/5015-Mirror-Navy-12-18-inch-w-logo-1-300x300.png'],
            ['name' => 'Mirror Burgundy',    'texture' => $mirror, 'family' => 'Reds',    'hex' => '#6A1F30', 'pms' => null, 'sort_order' => 890, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2023/05/5016-Mirror-Burgundy-12-18-inch-w-logo-1-300x300.png'],

            // METALLIC — 3 from the website + 12 spreadsheet-only colors (11" line). Hex estimates for the latter; no images.
            ['name' => 'Metallic Gold',           'texture' => $metallic, 'family' => 'Golds',   'hex' => '#C8A951', 'pms' => null, 'sort_order' => 1050, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2025/03/7002-Metallic-Gold-12-inch-w-logo-300x300.png'],
            ['name' => 'Metallic Silver',         'texture' => $metallic, 'family' => 'Silvers', 'hex' => '#A8A9AD', 'pms' => null, 'sort_order' => 1060, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2025/03/7003-Metallic-Silver-12-inch-w-logo-300x300.png'],
            ['name' => 'Metallic Rose Gold',      'texture' => $metallic, 'family' => 'Pinks',   'hex' => '#D4A097', 'pms' => null, 'sort_order' => 1070, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2025/03/7004-Metallic-Rose-Gold-12-inch-w-logo-300x300.png'],
            ['name' => 'Metallic Onyx Black',     'texture' => $metallic, 'family' => 'Blacks',  'hex' => '#231F20', 'pms' => null, 'sort_order' => 1080, 'image' => null],
            ['name' => 'Metallic Red',            'texture' => $metallic, 'family' => 'Reds',    'hex' => '#C5302A', 'pms' => null, 'sort_order' => 1085, 'image' => null],
            ['name' => 'Metallic Orange',         'texture' => $metallic, 'family' => 'Oranges', 'hex' => '#FF8038', 'pms' => null, 'sort_order' => 1086, 'image' => null],
            ['name' => 'Metallic Yellow',         'texture' => $metallic, 'family' => 'Yellows', 'hex' => '#F9DD16', 'pms' => null, 'sort_order' => 1087, 'image' => null],
            ['name' => 'Metallic Goldenrod',      'texture' => $metallic, 'family' => 'Yellows', 'hex' => '#FFCD00', 'pms' => null, 'sort_order' => 1088, 'image' => null],
            ['name' => 'Metallic Lime',           'texture' => $metallic, 'family' => 'Greens',  'hex' => '#C8E400', 'pms' => null, 'sort_order' => 1089, 'image' => null],
            ['name' => 'Metallic Emerald Green',  'texture' => $metallic, 'family' => 'Greens',  'hex' => '#009A44', 'pms' => null, 'sort_order' => 1091, 'image' => null],
            ['name' => 'Metallic Turquoise',      'texture' => $metallic, 'family' => 'Blues',   'hex' => '#009CA6', 'pms' => null, 'sort_order' => 1092, 'image' => null],
            ['name' => 'Metallic Sapphire Blue',  'texture' => $metallic, 'family' => 'Blues',   'hex' => '#005E8E', 'pms' => null, 'sort_order' => 1093, 'image' => null],
            ['name' => 'Metallic Midnight Blue',  'texture' => $metallic, 'family' => 'Blues',   'hex' => '#1B3D6B', 'pms' => null, 'sort_order' => 1094, 'image' => null],
            ['name' => 'Metallic Violet',         'texture' => $metallic, 'family' => 'Purples', 'hex' => '#6B5BC8', 'pms' => null, 'sort_order' => 1095, 'image' => null],
            ['name' => 'Metallic Fuchsia',        'texture' => $metallic, 'family' => 'Pinks',   'hex' => '#DB3EB1', 'pms' => null, 'sort_order' => 1096, 'image' => null],

            // PEARL
            ['name' => 'Pearl Pink',     'texture' => $pearl, 'family' => 'Pinks',   'hex' => '#F4B8C4', 'pms' => null, 'sort_order' => 900, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2025/03/7005-Pearl-Pink-12-inch-w-logo-300x300.png'],
            ['name' => 'Pearl Sky Blue', 'texture' => $pearl, 'family' => 'Blues',   'hex' => '#B5D4E8', 'pms' => null, 'sort_order' => 910, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2025/03/7006-Pearl-Sky-Blue-12-inch-w-logo-300x300.png'],
            ['name' => 'Pearl Lemon',    'texture' => $pearl, 'family' => 'Yellows', 'hex' => '#F4E48C', 'pms' => null, 'sort_order' => 920, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2024/10/7007-Pearl-Lemon-12-inch-w-logo-300x300.png'],
            ['name' => 'Pearl Green',    'texture' => $pearl, 'family' => 'Greens',  'hex' => '#9FCFA0', 'pms' => null, 'sort_order' => 930, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2024/10/7008-Pearl-Green-12-inch-w-logo-300x300.png'],
            ['name' => 'Pearl Salmon',   'texture' => $pearl, 'family' => 'Pinks',   'hex' => '#F4C0A8', 'pms' => null, 'sort_order' => 940, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2024/10/7009-Pearl-Salmon-12-inch-w-logo-300x300.png'],
            ['name' => 'Pearl Lilac',    'texture' => $pearl, 'family' => 'Purples', 'hex' => '#C9B8D9', 'pms' => null, 'sort_order' => 950, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2024/10/7010-Pearl-Lilac-12-inch-w-logo-300x300.png'],
            ['name' => 'Pearl White',    'texture' => $pearl, 'family' => 'Whites',  'hex' => '#F5F0E8', 'pms' => null, 'sort_order' => 955, 'image' => null],

            // OPAQUE SATIN
            ['name' => 'Opaque Satin Snow White', 'texture' => $opaqueSatin, 'family' => 'Whites', 'hex' => '#F8F6F1', 'pms' => null, 'sort_order' => 1090, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2024/10/7050-Opaque-Satin-Snow-White-12-18-inch-w-logo-2-300x300.png'],

            // CRYSTAL — transparent.
            ['name' => 'Clear Transparent',  'texture' => $crystal, 'family' => 'Clears',  'hex' => null,      'pms' => null, 'sort_order' => 970,  'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2021/01/seffaf-2-300x300.jpg'],
            ['name' => 'Crystal Yellow',     'texture' => $crystal, 'family' => 'Yellows', 'hex' => '#F4E040', 'pms' => null, 'sort_order' => 980,  'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2021/01/sari-300x300.png'],
            ['name' => 'Crystal Fuchsia',    'texture' => $crystal, 'family' => 'Pinks',   'hex' => '#D44390', 'pms' => null, 'sort_order' => 990,  'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2021/01/pembe-300x300.png'],
            ['name' => 'Crystal Burgundy',   'texture' => $crystal, 'family' => 'Reds',    'hex' => '#8F1D3F', 'pms' => null, 'sort_order' => 1000, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2021/01/bordo-300x300.png'],
            ['name' => 'Crystal Turquoise',  'texture' => $crystal, 'family' => 'Blues',   'hex' => '#009A8B', 'pms' => null, 'sort_order' => 1010, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2021/01/yesil-300x300.png'],
            ['name' => 'Crystal Blue',       'texture' => $crystal, 'family' => 'Blues',   'hex' => '#1E78C8', 'pms' => null, 'sort_order' => 1020, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2021/01/mavi-300x300.png'],
            ['name' => 'Crystal Violet',     'texture' => $crystal, 'family' => 'Purples', 'hex' => '#5E2D86', 'pms' => null, 'sort_order' => 1030, 'image' => 'https://en.kalisan.com.tr/wp-content/uploads/2021/01/mor-300x300.png'],

            // AURA — Kalisan's iridescent satin line. Hex estimates; images deferred.
            ['name' => 'Aura Beige Cream',   'texture' => $aura, 'family' => 'Whites',  'hex' => '#E8D8C0', 'pms' => null, 'sort_order' => 1100, 'image' => null],
            ['name' => 'Aura Ice Blue',      'texture' => $aura, 'family' => 'Blues',   'hex' => '#C8DCEA', 'pms' => null, 'sort_order' => 1110, 'image' => null],
            ['name' => 'Aura Ice Mint',      'texture' => $aura, 'family' => 'Greens',  'hex' => '#C8E4D2', 'pms' => null, 'sort_order' => 1120, 'image' => null],
            ['name' => 'Aura Ivory White',   'texture' => $aura, 'family' => 'Whites',  'hex' => '#F2EBDC', 'pms' => null, 'sort_order' => 1130, 'image' => null],
            ['name' => 'Aura Lavender Fog',  'texture' => $aura, 'family' => 'Purples', 'hex' => '#CFC4D8', 'pms' => null, 'sort_order' => 1140, 'image' => null],
            ['name' => 'Aura Antique Gold',  'texture' => $aura, 'family' => 'Golds',   'hex' => '#C8A878', 'pms' => null, 'sort_order' => 1150, 'image' => null],

            // ASSORTMENTS — one Color per named assortment, pinned to the matching texture.
            // hex = null (no single representative color); display falls back to the family swatch.
            ['name' => 'Standard Assorted',             'texture' => $standard, 'family' => 'Assortment', 'hex' => null, 'pms' => null, 'sort_order' => 1200, 'image' => null],
            ['name' => 'Standard Carnival Assortment',  'texture' => $standard, 'family' => 'Assortment', 'hex' => null, 'pms' => null, 'sort_order' => 1210, 'image' => null],
            ['name' => 'Standard Rainbow Assortment',   'texture' => $standard, 'family' => 'Assortment', 'hex' => null, 'pms' => null, 'sort_order' => 1220, 'image' => null],
            ['name' => 'Standard Character Assortment', 'texture' => $standard, 'family' => 'Assortment', 'hex' => null, 'pms' => null, 'sort_order' => 1230, 'image' => null],
            ['name' => 'Macaron Assorted',              'texture' => $macaron,  'family' => 'Assortment', 'hex' => null, 'pms' => null, 'sort_order' => 1240, 'image' => null],
            ['name' => 'Macaron Pale Assorted',         'texture' => $macaron,  'family' => 'Assortment', 'hex' => null, 'pms' => null, 'sort_order' => 1250, 'image' => null],
            ['name' => 'Mirror Assorted',               'texture' => $mirror,   'family' => 'Assortment', 'hex' => null, 'pms' => null, 'sort_order' => 1260, 'image' => null],
            ['name' => 'Crystal Assorted',              'texture' => $crystal,  'family' => 'Assortment', 'hex' => null, 'pms' => null, 'sort_order' => 1270, 'image' => null],
            ['name' => 'Retro Assorted',                'texture' => $retro,    'family' => 'Assortment', 'hex' => null, 'pms' => null, 'sort_order' => 1280, 'image' => null],
        ];
    }
}
