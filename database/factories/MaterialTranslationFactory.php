<?php

namespace Database\Factories;

use App\Models\Material;
use App\Models\MaterialTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaterialTranslation>
 */
class MaterialTranslationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'material_id' => Material::factory(),
            'locale' => 'en',
            'name' => fake()->word(),
        ];
    }
}
