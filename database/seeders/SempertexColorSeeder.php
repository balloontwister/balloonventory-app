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

class SempertexColorSeeder extends Seeder
{
    /**
     * Cluster images are committed under storage/app/public/color-images/sempertex/clusters/.
     * Single product images are downloaded from cdn.shopify.com (sempertex.com)
     * on first run, cached on disk so re-runs are no-ops.
     */
    private const CLUSTER_DIR = 'color-images/sempertex/clusters';

    private const SINGLE_DIR = 'color-images/sempertex/singles';

    private const MANIFEST_PATH = 'database/data/sempertex_color_manifest.json';

    public function run(): void
    {
        $sempertex = Brand::where('name', 'Sempertex')->firstOrFail();
        $latex = Material::where('name', 'Latex')->firstOrFail();

        $textures = Texture::where('brand_id', $sempertex->id)->get()->keyBy('name');
        $families = ColorFamily::pluck('id', 'name');

        $manifest = $this->loadManifest();

        foreach ($manifest as $row) {
            $texture = $textures[$row['texture']] ?? null;

            if ($texture === null) {
                // SempertexTextureSeeder is a prerequisite — surface missing textures loudly.
                throw new \RuntimeException("Sempertex texture not found: {$row['texture']} (color {$row['name']})");
            }

            Color::updateOrCreate(
                ['name' => $row['name'], 'brand_id' => $sempertex->id],
                [
                    'color_family_id' => $families[$row['family']] ?? null,
                    'brand_id' => $sempertex->id,
                    'material_id' => $latex->id,
                    'texture_id' => $texture->id,
                    'color_hex' => $row['hex'],
                    'pms_value' => $row['pms'],
                    'cluster_image_file_path' => $this->clusterPath($row['name'], $row['cluster_image_filename']),
                    'single_image_file_path' => $this->fetchSingle($row['name'], $row['single_image_url'] ?? null),
                    'sort_order' => $row['sort_order'],
                ],
            );
        }
    }

    /**
     * @return array<int, array{name: string, texture: string, family: string, pms: ?string, hex: ?string, sort_order: int, cluster_image_filename: ?string, single_image_url: ?string, shopify_handle: ?string}>
     */
    private function loadManifest(): array
    {
        $path = base_path(self::MANIFEST_PATH);

        if (! is_file($path)) {
            throw new \RuntimeException('Sempertex manifest not found at '.self::MANIFEST_PATH);
        }

        return json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Storage-relative path to the resized cluster image. The file lives at
     * storage/app/public/color-images/sempertex/clusters/{slug}.jpg and is
     * committed to the repo, so the path is always valid in any environment
     * that deployed via git.
     */
    private function clusterPath(string $colorName, ?string $sourceFilename): ?string
    {
        if ($sourceFilename === null) {
            return null;
        }

        return self::CLUSTER_DIR.'/'.Str::slug($colorName).'.jpg';
    }

    /**
     * Download the single-balloon product image from cdn.shopify.com and
     * return its storage-relative path. Returns null on any failure — the
     * color row is still created and the swatch falls back to cluster/hex.
     */
    private function fetchSingle(string $colorName, ?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $extension = pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION) ?: 'jpg';
        $relative = self::SINGLE_DIR.'/'.Str::slug($colorName).'.'.$extension;

        if (Storage::disk('public')->exists($relative)) {
            return $relative;
        }

        try {
            $response = Http::timeout(15)->get($url);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        Storage::disk('public')->put($relative, $response->body());

        return $relative;
    }
}
