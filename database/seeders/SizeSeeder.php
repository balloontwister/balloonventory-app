<?php

namespace Database\Seeders;

use App\Models\Size;
use Illuminate\Database\Seeder;

class SizeSeeder extends Seeder
{
    public function run(): void
    {
        $sizes = [
            // Round latex — small
            ['name' => '5-inch',  'size_category' => 'small',          'sort_order' => 10],
            ['name' => '6-inch',  'size_category' => 'small',          'sort_order' => 20],
            // Round latex — medium
            ['name' => '11-inch', 'size_category' => 'medium',         'sort_order' => 30],
            ['name' => '12-inch', 'size_category' => 'medium',         'sort_order' => 40],
            // Round latex — large
            ['name' => '16-inch', 'size_category' => 'large',          'sort_order' => 50],
            ['name' => '24-inch', 'size_category' => 'large',          'sort_order' => 60],
            // Round latex — giant
            ['name' => '36-inch', 'size_category' => 'giant',          'sort_order' => 70],
            ['name' => '72-inch', 'size_category' => 'giant',          'sort_order' => 80],
            // Modeling / twisting — small
            ['name' => '160',     'size_category' => 'small_modeling', 'sort_order' => 90],
            ['name' => '260',     'size_category' => 'small_modeling', 'sort_order' => 100],
            // Modeling / twisting — large
            ['name' => '350',     'size_category' => 'large_modeling', 'sort_order' => 110],
            ['name' => '360',     'size_category' => 'large_modeling', 'sort_order' => 120],
            ['name' => '646',     'size_category' => 'large_modeling', 'sort_order' => 130],
            ['name' => '660',     'size_category' => 'large_modeling', 'sort_order' => 140],
        ];

        foreach ($sizes as $data) {
            Size::firstOrCreate(['name' => $data['name']], $data);
        }
    }
}
