<?php

namespace Database\Factories;

use App\Models\BalloonList;
use App\Models\ListItem;
use App\Models\Sku;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ListItem>
 */
class ListItemFactory extends Factory
{
    protected $model = ListItem::class;

    public function definition(): array
    {
        return [
            'list_id' => BalloonList::factory(),
            'sku_id' => Sku::factory(),
            'planned_quantity' => null,
            'sort_order' => 0,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
