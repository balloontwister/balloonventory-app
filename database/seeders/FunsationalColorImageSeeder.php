<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Color;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Attaches per-colour product images to Funsational colours, downloaded at seed
 * time from the manufacturer (pioneerballoon.com/funsational — Todd's chosen
 * image source). The colour → image-URL map lives in
 * database/data/funsational_color_images.json and is meant to be extended: the
 * `unmatched` list there (Crystal/Pastel, which Pioneer's page lacks, plus a few
 * renames) is topped up by hand, then this seeder re-run.
 *
 * Follows the Kalisan/Sempertex image-attached pattern: download → store on the
 * public disk → persist the relative path on `colors.single_image_file_path`.
 * Idempotent (skips a colour whose image is already on disk) and fault-tolerant
 * (a failed fetch leaves the colour row untouched, only the image missing).
 */
class FunsationalColorImageSeeder extends Seeder
{
    public function run(): void
    {
        $brand = Brand::where('name', 'Funsational')->firstOrFail();
        $colors = Color::where('brand_id', $brand->id)->get()->keyBy('name');

        $map = json_decode(file_get_contents(database_path('data/funsational_color_images.json')), true);

        foreach (($map['images'] ?? []) as $colorName => $url) {
            $color = $colors->get($colorName);
            if (! $color) {
                continue;
            }

            $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION)) ?: 'jpg';
            $path = 'color-images/funsational/'.Str::slug($colorName).'.'.$ext;

            // Idempotent: already downloaded + linked → skip the fetch.
            if ($color->single_image_file_path === $path && Storage::disk('public')->exists($path)) {
                continue;
            }

            try {
                $response = Http::timeout(15)->get($url);
                if (! $response->successful() || $response->body() === '') {
                    continue;
                }
                Storage::disk('public')->put($path, $response->body());
            } catch (\Throwable) {
                continue; // leave the colour row intact; only the image is missing
            }

            $color->update(['single_image_file_path' => $path]);
        }
    }
}
