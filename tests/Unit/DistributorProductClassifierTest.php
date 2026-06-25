<?php

namespace Tests\Unit;

use App\Services\Distributors\DistributorProductClassifier;
use Tests\TestCase;

class DistributorProductClassifierTest extends TestCase
{
    /**
     * @param  array<string, array<int, string>>  $attributes
     */
    private function extraction(array $attributes): array
    {
        return [
            'has_recipe' => true,
            'attributes' => $attributes,
            'row_count' => array_sum(array_map('count', $attributes)),
            'missing_required' => [],
            'ok' => true,
        ];
    }

    public function test_solid_latex(): void
    {
        $type = (new DistributorProductClassifier)->classify($this->extraction([
            'Brand' => ['Kalisan'], 'Industry' => ['Balloons'],
            'Balloon Material' => ['Latex'], 'Color' => ['Clear'],
            'Balloon Type / Shape' => ['Solid Color'],
        ]));

        $this->assertSame(DistributorProductClassifier::SOLID_LATEX, $type);
    }

    public function test_foil_from_material(): void
    {
        $type = (new DistributorProductClassifier)->classify($this->extraction([
            'Brand' => ['Betallic'], 'Industry' => ['Balloons'],
            'Balloon Material' => ['Foil / Non-Foil'], 'Balloon Type / Shape' => ['Shape'],
            'Occasion / Theme' => ['Farm / Animal'],
        ]));

        $this->assertSame(DistributorProductClassifier::FOIL, $type);
    }

    public function test_printed_latex_when_themed(): void
    {
        $type = (new DistributorProductClassifier)->classify($this->extraction([
            'Brand' => ['Qualatex'], 'Industry' => ['Balloons'],
            'Balloon Material' => ['Latex'], 'Occasion / Theme' => ['Birthday'],
        ]));

        $this->assertSame(DistributorProductClassifier::PRINTED, $type);
    }

    public function test_assortment_color(): void
    {
        $type = (new DistributorProductClassifier)->classify($this->extraction([
            'Brand' => ['Qualatex'], 'Industry' => ['Balloons'],
            'Balloon Material' => ['Latex'], 'Color' => ['Assortment'],
        ]));

        $this->assertSame(DistributorProductClassifier::ASSORTMENT, $type);
    }

    public function test_accessory_when_not_balloon_industry(): void
    {
        $type = (new DistributorProductClassifier)->classify($this->extraction([
            'Brand' => ['Conwin'], 'Industry' => ['Accessories'],
        ]));

        $this->assertSame(DistributorProductClassifier::ACCESSORY, $type);
    }

    public function test_non_balloon_when_no_table(): void
    {
        $type = (new DistributorProductClassifier)->classify([
            'has_recipe' => true, 'attributes' => [], 'row_count' => 0,
            'missing_required' => [], 'ok' => false,
        ]);

        $this->assertSame(DistributorProductClassifier::NON_BALLOON, $type);
    }
}
