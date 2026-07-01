<?php

namespace Database\Seeders;

use App\Models\BalloonSize;
use App\Models\Brand;
use App\Models\Material;
use App\Models\Shape;
use App\Models\Size;
use Illuminate\Database\Seeder;

/**
 * Brand-scoped Funsational balloon sizes (the `(F)` suffix). Funsational's solid
 * latex comes in 7", 12", and 17" rounds (per distributor staging). The
 * `size_id` FK is a coarse bucket (the balloon_size NAME carries the real inch
 * value), so each maps to the nearest existing Size row — same loose convention
 * as DecomexBalloonSizeSeeder (e.g. 12" → 11-inch). firstOrCreate keeps it
 * idempotent.
 */
class FunsationalBalloonSizeSeeder extends Seeder
{
    public function run(): void
    {
        $funsational = Brand::where('name', 'Funsational')->firstOrFail();
        $latex = Material::where('name', 'Latex')->firstOrFail();
        $round = Shape::where('name', 'Round')->firstOrFail();

        $size5 = Size::where('name', '5-inch')->first()?->id;
        $size11 = Size::where('name', '11-inch')->first()?->id;
        $size16 = Size::where('name', '16-inch')->first()?->id;

        $rows = [
            ['name' => '7-inch (F)',  'size_id' => $size5,  'sort_order' => 900],
            ['name' => '12-inch (F)', 'size_id' => $size11, 'sort_order' => 905],
            ['name' => '17-inch (F)', 'size_id' => $size16, 'sort_order' => 910],
        ];

        foreach ($rows as $row) {
            BalloonSize::firstOrCreate(
                ['brand_id' => $funsational->id, 'material_id' => $latex->id, 'name' => $row['name']],
                [
                    'shape_id' => $round->id,
                    'size_id' => $row['size_id'],
                    'sort_order' => $row['sort_order'],
                ],
            );
        }
    }
}
