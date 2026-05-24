<?php

namespace Database\Seeders;

use App\Models\BalloonSize;
use App\Models\Brand;
use App\Models\Material;
use App\Models\Shape;
use App\Models\Size;
use Illuminate\Database\Seeder;

class KalisanBalloonSizeSeeder extends Seeder
{
    /**
     * Codifies the 12 Kalisan-scoped balloon_size rows that production has
     * (UI-created). firstOrCreate keeps this idempotent so production is a
     * no-op; in tests and fresh installs it sets up the FK targets the
     * Kalisan SKU importer expects.
     */
    public function run(): void
    {
        $kalisan = Brand::where('name', 'Kalisan')->firstOrFail();
        $latex = Material::where('name', 'Latex')->firstOrFail();

        $round = Shape::where('name', 'Round')->firstOrFail();
        $link = Shape::where('name', 'Link')->firstOrFail();
        $nonRound = Shape::where('name', 'Non-round')->firstOrFail();
        $heart = Shape::where('name', 'Heart')->firstOrFail();

        $size5 = Size::where('name', '5-inch')->first()?->id;
        $size11 = Size::where('name', '11-inch')->first()?->id;
        $size18 = Size::where('name', '18-inch')->first()?->id;
        $size24 = Size::where('name', '24-inch')->first()?->id;
        $size36 = Size::where('name', '36-inch')->first()?->id;
        $size160 = Size::where('name', '160')->first()?->id;
        $size260 = Size::where('name', '260')->first()?->id;
        $size360 = Size::where('name', '360')->first()?->id;

        $rows = [
            ['name' => '5-inch (K)',  'shape_id' => $round->id,    'size_id' => $size5,   'sort_order' => 510],
            ['name' => '10-inch (K)', 'shape_id' => $round->id,    'size_id' => $size11,  'sort_order' => 515],
            ['name' => '12-inch (K)', 'shape_id' => $round->id,    'size_id' => $size11,  'sort_order' => 520],
            ['name' => '18-inch (K)', 'shape_id' => $round->id,    'size_id' => $size18,  'sort_order' => 525],
            ['name' => '24-inch (K)', 'shape_id' => $round->id,    'size_id' => $size24,  'sort_order' => 530],
            ['name' => '36-inch (K)', 'shape_id' => $round->id,    'size_id' => $size36,  'sort_order' => 535],
            ['name' => '6" K-Link',   'shape_id' => $link->id,     'size_id' => $size5,   'sort_order' => 540],
            ['name' => '12" K-Link',  'shape_id' => $link->id,     'size_id' => $size11,  'sort_order' => 545],
            // 12-inch Heart for Kalisan — intentionally no (K) suffix on the name
            // (the brand_id still scopes it to Kalisan).
            ['name' => '12-inch',     'shape_id' => $heart->id,    'size_id' => $size11,  'sort_order' => 550],
            ['name' => '160K',        'shape_id' => $nonRound->id, 'size_id' => $size160, 'sort_order' => 560],
            ['name' => '260K',        'shape_id' => $nonRound->id, 'size_id' => $size260, 'sort_order' => 570],
            ['name' => '360K',        'shape_id' => $nonRound->id, 'size_id' => $size360, 'sort_order' => 580],
        ];

        foreach ($rows as $row) {
            BalloonSize::firstOrCreate(
                ['brand_id' => $kalisan->id, 'material_id' => $latex->id, 'name' => $row['name']],
                [
                    'shape_id' => $row['shape_id'],
                    'size_id' => $row['size_id'],
                    'sort_order' => $row['sort_order'],
                ],
            );
        }
    }
}
