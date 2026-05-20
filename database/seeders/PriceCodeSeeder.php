<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\PriceCode;
use Illuminate\Database\Seeder;

class PriceCodeSeeder extends Seeder
{
    public function run(): void
    {
        // Skip once the table holds data — catalog data is curated by hand in production.
        if (PriceCode::withTrashed()->exists()) {
            return;
        }

        // Only seed if brands exist. Each brand gets a default price code.
        $brands = Brand::all();

        foreach ($brands as $brand) {
            PriceCode::firstOrCreate(
                ['brand_id' => $brand->id, 'code' => mb_strtolower($brand->abbreviation)],
                ['sort_order' => 0],
            );
        }
    }
}
