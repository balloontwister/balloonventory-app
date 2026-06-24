<?php

namespace Database\Seeders;

use App\Models\Distributor;
use Illuminate\Database\Seeder;

class DistributorSeeder extends Seeder
{
    public function run(): void
    {
        $distributors = [
            [
                'name' => 'BargainBalloons',
                'slug' => 'bargain-balloons',
                'platform_type' => 'shopify',
                'base_url' => 'https://bargainballoons.com',
                'config' => ['collection_handle' => 'all', 'has_json_api' => true],
                'is_active' => true,
                'sort_order' => 0,
            ],
            [
                'name' => 'Larocks',
                'slug' => 'larocks',
                'platform_type' => 'bigcommerce',
                'base_url' => 'https://larocks.com',
                'config' => [],
                'is_active' => true,
                'sort_order' => 1,
            ],
        ];

        foreach ($distributors as $data) {
            Distributor::firstOrCreate(
                ['slug' => $data['slug']],
                $data,
            );
        }
    }
}
