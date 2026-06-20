<?php

namespace Database\Seeders;

use App\Models\BalloonSize;
use App\Models\Brand;
use App\Models\Material;
use App\Models\Shape;
use App\Models\Size;
use Illuminate\Database\Seeder;

class DecomexBalloonSizeSeeder extends Seeder
{
    /**
     * Codifies the 16 Decomex-scoped balloon_size rows that production has
     * (UI-created). firstOrCreate keeps this idempotent so production is a
     * no-op; in tests and fresh installs it sets up the FK targets the
     * Decomex SKU importer expects.
     */
    public function run(): void
    {
        $decomex = Brand::where('name', 'Decomex')->firstOrFail();
        $latex = Material::where('name', 'Latex')->firstOrFail();

        $round = Shape::where('name', 'Round')->firstOrFail();
        $heart = Shape::where('name', 'Heart')->firstOrFail();
        $link = Shape::where('name', 'Link')->firstOrFail();
        $nonRound = Shape::where('name', 'Non-round')->firstOrFail();

        $size5 = Size::where('name', '5-inch')->first()?->id;
        $size11 = Size::where('name', '11-inch')->first()?->id;
        $size18 = Size::where('name', '18-inch')->first()?->id;
        $size24 = Size::where('name', '24-inch')->first()?->id;
        $size36 = Size::where('name', '36-inch')->first()?->id;
        $size160 = Size::where('name', '160')->first()?->id;
        $size260 = Size::where('name', '260')->first()?->id;
        $size360 = Size::where('name', '360')->first()?->id;
        $size660 = Size::where('name', '660')->first()?->id;

        $rows = [
            // Round sizes
            ['name' => '5-inch (D)',         'shape_id' => $round->id,    'size_id' => $size5,   'sort_order' => 905],
            ['name' => '9-inch (D)',         'shape_id' => $round->id,    'size_id' => $size11,  'sort_order' => 910],
            ['name' => '12-inch (D)',        'shape_id' => $round->id,    'size_id' => $size11,  'sort_order' => 915],
            ['name' => '18-inch (D)',        'shape_id' => $round->id,    'size_id' => $size18,  'sort_order' => 920],
            ['name' => '26-inch(D)',         'shape_id' => $round->id,    'size_id' => $size24,  'sort_order' => 925],
            ['name' => '36-inch (D)',        'shape_id' => $round->id,    'size_id' => $size36,  'sort_order' => 930],
            // Heart sizes
            ['name' => '7-inch Heart (D)',   'shape_id' => $heart->id,    'size_id' => $size5,   'sort_order' => 935],
            ['name' => '11-inch Heart (D)',  'shape_id' => $heart->id,    'size_id' => $size11,  'sort_order' => 940],
            ['name' => '18-inch Heart (D)',  'shape_id' => $heart->id,    'size_id' => $size18,  'sort_order' => 945],
            // Link sizes
            ['name' => '6-inch Link (D)',    'shape_id' => $link->id,     'size_id' => $size5,   'sort_order' => 950],
            ['name' => '11-inch Link (D)',   'shape_id' => $link->id,     'size_id' => $size11,  'sort_order' => 955],
            ['name' => '18-inch Link (D)',   'shape_id' => $link->id,     'size_id' => $size18,  'sort_order' => 960],
            // Twisting sizes
            ['name' => '160 (D)',            'shape_id' => $nonRound->id, 'size_id' => $size160, 'sort_order' => 965],
            ['name' => '260 (D)',            'shape_id' => $nonRound->id, 'size_id' => $size260, 'sort_order' => 970],
            ['name' => '360 (D)',            'shape_id' => $nonRound->id, 'size_id' => $size360, 'sort_order' => 975],
            ['name' => '660 (D)',            'shape_id' => $nonRound->id, 'size_id' => $size660, 'sort_order' => 980],
        ];

        foreach ($rows as $row) {
            BalloonSize::firstOrCreate(
                ['brand_id' => $decomex->id, 'material_id' => $latex->id, 'name' => $row['name']],
                [
                    'shape_id' => $row['shape_id'],
                    'size_id' => $row['size_id'],
                    'sort_order' => $row['sort_order'],
                ],
            );
        }
    }
}
