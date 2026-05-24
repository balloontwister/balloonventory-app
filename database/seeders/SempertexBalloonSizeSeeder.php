<?php

namespace Database\Seeders;

use App\Models\BalloonSize;
use App\Models\Brand;
use App\Models\Material;
use App\Models\Shape;
use App\Models\Size;
use Illuminate\Database\Seeder;

class SempertexBalloonSizeSeeder extends Seeder
{
    /**
     * Codifies the Sempertex-scoped balloon_size rows that production has
     * (UI-created). firstOrCreate keeps this idempotent so production is a
     * no-op; in tests and fresh installs it sets up the FK targets the
     * Sempertex SKU importer expects.
     *
     * Two production rows are also corrected here:
     *   - "160- S" → "160-S" (stray space)
     *   - "260-S"  → shape Non-round (was Heart)
     */
    public function run(): void
    {
        $sempertex = Brand::where('name', 'Sempertex')->firstOrFail();
        $latex = Material::where('name', 'Latex')->firstOrFail();

        $round = Shape::where('name', 'Round')->firstOrFail();
        $link = Shape::where('name', 'Link')->firstOrFail();
        $nonRound = Shape::where('name', 'Non-round')->firstOrFail();
        $heart = Shape::where('name', 'Heart')->firstOrFail();

        $size5 = Size::where('name', '5-inch')->first()?->id;
        $size11 = Size::where('name', '11-inch')->first()?->id;
        $size16 = Size::where('name', '16-inch')->first()?->id;
        $size18 = Size::where('name', '18-inch')->first()?->id;
        $size24 = Size::where('name', '24-inch')->first()?->id;
        $size36 = Size::where('name', '36-inch')->first()?->id;
        $size160 = Size::where('name', '160')->first()?->id;
        $size260 = Size::where('name', '260')->first()?->id;
        $size360 = Size::where('name', '360')->first()?->id;
        $size660 = Size::where('name', '660')->first()?->id;

        // Fix the two pre-existing production rows in place: rename "160- S"
        // to "160-S" and switch 260-S from Heart to Non-round (it's a twist
        // modeling balloon, not a heart). Both are no-ops if already correct.
        BalloonSize::where('brand_id', $sempertex->id)->where('name', '160- S')->update(['name' => '160-S']);
        BalloonSize::where('brand_id', $sempertex->id)->where('name', '260-S')->update(['shape_id' => $nonRound->id]);

        $rows = [
            ['name' => 'R-5',     'shape_id' => $round->id,    'size_id' => $size5,   'sort_order' => 600],
            ['name' => 'R-9',     'shape_id' => $round->id,    'size_id' => $size11,  'sort_order' => 605],
            ['name' => 'R-12',    'shape_id' => $round->id,    'size_id' => $size11,  'sort_order' => 610],
            ['name' => 'R-18',    'shape_id' => $round->id,    'size_id' => $size16,  'sort_order' => 615],
            ['name' => 'R-24',    'shape_id' => $round->id,    'size_id' => $size24,  'sort_order' => 620],
            ['name' => 'R-36',    'shape_id' => $round->id,    'size_id' => $size36,  'sort_order' => 625],
            ['name' => 'C-6',     'shape_id' => $heart->id,    'size_id' => $size5,   'sort_order' => 630],
            ['name' => 'C-12',    'shape_id' => $heart->id,    'size_id' => $size11,  'sort_order' => 635],
            ['name' => 'C-14',    'shape_id' => $heart->id,    'size_id' => $size16,  'sort_order' => 640],
            ['name' => 'LOL-6',   'shape_id' => $link->id,     'size_id' => $size5,   'sort_order' => 645],
            ['name' => 'LOL-12',  'shape_id' => $link->id,     'size_id' => $size11,  'sort_order' => 650],
            ['name' => 'LOL-660', 'shape_id' => $nonRound->id, 'size_id' => $size660, 'sort_order' => 655],
            ['name' => '160-S',   'shape_id' => $nonRound->id, 'size_id' => $size160, 'sort_order' => 660],
            ['name' => '260-S',   'shape_id' => $nonRound->id, 'size_id' => $size260, 'sort_order' => 665],
            ['name' => '360',     'shape_id' => $nonRound->id, 'size_id' => $size360, 'sort_order' => 670],
        ];

        foreach ($rows as $row) {
            BalloonSize::firstOrCreate(
                ['brand_id' => $sempertex->id, 'material_id' => $latex->id, 'name' => $row['name']],
                [
                    'shape_id' => $row['shape_id'],
                    'size_id' => $row['size_id'],
                    'sort_order' => $row['sort_order'],
                ],
            );
        }
    }
}
