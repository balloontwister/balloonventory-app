<?php

namespace Database\Seeders;

use App\Models\BalloonSize;
use App\Models\Brand;
use App\Models\Material;
use App\Models\Shape;
use App\Models\Size;
use Illuminate\Database\Seeder;

/**
 * Canonical TufTex foil balloon_size combos — the FK targets the foil SKU
 * importer (catalog:import-tuftex-foils) resolves by name.
 *
 * Supersedes six legacy hand-created scaffolding rows that predate the foil
 * sizes added by FoilCatalogSeeder: they were Round-only, mapped to the
 * nearest available size (e.g. "18-inch" -> 16-inch, "30-inch-Foil-T" ->
 * 36-inch), and used inconsistent names. They carry zero SKUs, so this seeder
 * soft-deletes them and plants the correct, complete set under the
 * "{size}-inch Foil {shape}" naming scheme. Idempotent: the cleanup targets the
 * exact legacy names and the writes are keyed firstOrCreate.
 */
class TufTexFoilBalloonSizeSeeder extends Seeder
{
    /**
     * Legacy hand-created foil balloon_size names being replaced.
     *
     * @var list<string>
     */
    private const LEGACY_NAMES = [
        '18-inch', '24-inch-T', '26-inch-Foil-T', '28-inch', '30-inch-Foil-T', '34-inch',
    ];

    public function run(): void
    {
        $tuftex = Brand::where('name', 'TufTex')->firstOrFail();
        $foil = Material::where('name', 'Foil')->firstOrFail();

        $this->retireLegacyRows($tuftex->id, $foil->id);

        $shapes = Shape::where('material_id', $foil->id)
            ->get()
            ->keyBy('name');
        $sizes = Size::whereIn('name', [
            '18-inch', '22-inch', '24-inch', '25-inch', '26-inch', '28-inch',
            '30-inch', '34-inch', '36-inch',
        ])->get()->keyBy('name');

        // [generic size name, foil shape name, sort_order]
        $combos = [
            ['18-inch', 'Round',  700],
            ['22-inch', 'Round',  701],
            ['24-inch', 'Round',  702],
            ['24-inch', 'Square', 703],
            ['24-inch', 'Star',   704],
            ['24-inch', 'Shaped', 705],
            ['25-inch', 'Shaped', 706],
            ['26-inch', 'Shaped', 707],
            ['28-inch', 'Shaped', 708],
            ['30-inch', 'Round',  709],
            ['34-inch', 'Round',  710],
            ['36-inch', 'Shaped', 711],
        ];

        foreach ($combos as [$sizeName, $shapeName, $sortOrder]) {
            $size = $sizes->get($sizeName);
            $shape = $shapes->get($shapeName);

            if (! $size || ! $shape) {
                // FoilCatalogSeeder must run first; skip rather than write a bad FK.
                continue;
            }

            $name = "{$sizeName} Foil {$shapeName}";

            BalloonSize::firstOrCreate(
                ['brand_id' => $tuftex->id, 'material_id' => $foil->id, 'name' => $name],
                [
                    'shape_id' => $shape->id,
                    'size_id' => $size->id,
                    'sort_order' => $sortOrder,
                ],
            );
        }
    }

    /**
     * Soft-delete the superseded scaffolding rows. Only acts on rows that carry
     * no SKUs, so an accidental match can never strand catalog data.
     */
    private function retireLegacyRows(string $brandId, string $foilId): void
    {
        BalloonSize::where('brand_id', $brandId)
            ->where('material_id', $foilId)
            ->whereIn('name', self::LEGACY_NAMES)
            ->whereDoesntHave('skus')
            ->each(fn (BalloonSize $row) => $row->delete());
    }
}
