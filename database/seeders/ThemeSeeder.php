<?php

namespace Database\Seeders;

use App\Models\Theme;
use Illuminate\Database\Seeder;

class ThemeSeeder extends Seeder
{
    public function run(): void
    {
        $themes = [
            ['name' => 'Holiday',   'sort_order' => 10],
            ['name' => 'Christmas', 'sort_order' => 20],
            ['name' => 'Halloween', 'sort_order' => 30],
            ['name' => 'Stars',     'sort_order' => 40],
            ['name' => 'Animal',    'sort_order' => 50],
            ['name' => 'Star Wars', 'sort_order' => 60],
            ['name' => 'Princess',  'sort_order' => 70],
            ['name' => 'Cartoon',   'sort_order' => 80],
            ['name' => 'Jungle',    'sort_order' => 90],
        ];

        foreach ($themes as $data) {
            Theme::firstOrCreate(['name' => $data['name']], $data);
        }
    }
}
