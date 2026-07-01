<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Color;
use App\Models\ColorFamily;
use App\Models\Material;
use App\Models\Texture;
use Illuminate\Database\Seeder;

/**
 * Funsational colours, sourced from distributor staging (Larocks / LA Balloons /
 * Joker) — see intake/funsational/. Like the Britetex import these are NAME-only:
 * no published hex/swatch, so the hex in the manifest is an approximation the
 * catalog renders as a swatch (multi-colour "Assortment" packs have none). The
 * matcher resolves a distributor's Color on the name alone, so seeding these is
 * what lets the pending Funsational proposals resolve.
 *
 * The colour list is a JSON manifest (database/data/funsational_color_manifest.json)
 * iterated here — the Sempertex pattern for a large palette. Names carry the
 * finish for non-standard textures ("Pearl Mint Green" on Pearl (F)); Standard
 * colours are the bare shade ("Red" on Standard (F)). Idempotent — re-run to add
 * colours a deeper crawl surfaces later.
 */
class FunsationalColorSeeder extends Seeder
{
    public function run(): void
    {
        $funsational = Brand::where('name', 'Funsational')->firstOrFail();
        $latex = Material::where('name', 'Latex')->firstOrFail();

        $families = ColorFamily::pluck('id', 'name');
        $textures = Texture::where('brand_id', $funsational->id)->pluck('id', 'name');

        $manifest = json_decode(file_get_contents(database_path('data/funsational_color_manifest.json')), true);

        foreach ($manifest['colors'] as $data) {
            $textureId = $textures[$data['texture']] ?? null;

            // The texture must exist (FunsationalTextureSeeder runs first). Skip
            // rather than fail so one stray finish can't abort the whole seed.
            if ($textureId === null) {
                continue;
            }

            Color::updateOrCreate(
                ['name' => $data['name'], 'brand_id' => $funsational->id],
                [
                    'color_family_id' => $families[$data['family']] ?? null,
                    'brand_id' => $funsational->id,
                    'material_id' => $latex->id,
                    'texture_id' => $textureId,
                    'color_hex' => $data['hex'],
                    'pms_value' => null,
                    'sort_order' => $data['sort_order'],
                ],
            );
        }
    }
}
