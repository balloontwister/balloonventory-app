<?php

namespace App\Console\Commands;

use App\Models\BalloonSize;
use App\Models\Brand;
use App\Models\Color;
use App\Models\Sku;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Imports Funsational SKUs from intake/funsational/funsational_skus.json.
 *
 * The manifest is built from distributor staging (Larocks / LA Balloons / Joker)
 * so each SKU carries the real **UPC** — the join key that lets the distributor
 * proposals reconcile and attach Reorder links on the next cluster run. The
 * `warehouse_sku` is the UPC's middle 5 digits (== Pioneer's Mfg#, validated
 * against pioneerballoon.com/funsational). Colour/size resolve to the Funsational
 * reference rows seeded by FunsationalColorSeeder / FunsationalBalloonSizeSeeder.
 *
 * Mirrors ImportKalisanSkus: dry-run by default, keyed on (brand_id,
 * warehouse_sku) for idempotency, links identical pack-size/count siblings.
 */
class ImportFunsationalSkus extends Command
{
    protected $signature = 'catalog:import-funsational
                            {--execute : Write to the database (omit for dry-run)}
                            {--path= : Override path to the SKU JSON file}';

    protected $description = 'Import Funsational SKUs from intake/funsational/funsational_skus.json';

    public function handle(): int
    {
        $dryRun = ! $this->option('execute');
        $jsonPath = $this->option('path') ?: base_path('intake/funsational/funsational_skus.json');

        if (! file_exists($jsonPath)) {
            $this->error("File not found: {$jsonPath}");

            return Command::FAILURE;
        }

        $manifest = json_decode(file_get_contents($jsonPath), true);
        $rows = $manifest['skus'] ?? [];

        $brand = Brand::where('name', 'Funsational')->firstOrFail();
        $balloonSizes = BalloonSize::where('brand_id', $brand->id)->get()->keyBy('name');
        $colors = Color::where('brand_id', $brand->id)->get()->keyBy('name');

        $existingByWarehouseSku = Sku::where('brand_id', $brand->id)
            ->whereNotNull('warehouse_sku')
            ->get()
            ->keyBy('warehouse_sku');

        $toInsert = [];
        $toUpdate = [];
        $warnings = [];

        foreach ($rows as $row) {
            $size = $balloonSizes->get($row['size_resolved']);
            if (! $size) {
                $warnings[] = "No balloon_size '{$row['size_resolved']}': {$row['name']}";

                continue;
            }

            $color = $colors->get($row['color_resolved']);
            if (! $color) {
                $warnings[] = "Color not found '{$row['color_resolved']}': {$row['name']}";

                continue;
            }

            $attrs = [
                'name' => $row['name'],
                'brand_id' => $brand->id,
                'color_id' => $color->id,
                'balloon_size_id' => $size->id,
                'material_id' => $size->material_id,
                'warehouse_sku' => $row['warehouse_sku'],
                'upc' => $row['upc'],
                'default_count_per_bag' => $row['count_per_bag'],
                'is_printed' => false,
                'is_active' => true,
            ];

            $existing = $existingByWarehouseSku->get($row['warehouse_sku']);

            if ($existing) {
                $changes = $this->diffSku($existing, $attrs);
                if ($changes) {
                    $toUpdate[] = ['sku' => $existing, 'changes' => $changes, 'row' => $row];
                }
            } else {
                $toInsert[] = ['attrs' => $attrs, 'row' => $row];
            }
        }

        if ($warnings) {
            $this->newLine();
            $this->warn('WARNINGS ('.count($warnings).'):');
            foreach ($warnings as $w) {
                $this->warn("  ✗ {$w}");
            }
        }

        if ($dryRun && $this->getOutput()->isVerbose()) {
            $this->newLine();
            $this->line('<info>INSERTS:</info>');
            foreach ($toInsert as $item) {
                $r = $item['row'];
                $this->line("  + [{$r['warehouse_sku']}] {$r['name']} → {$r['size_resolved']}, {$r['color_resolved']}, {$r['count_per_bag']}ct, upc={$r['upc']}");
            }
        }

        $this->newLine();
        $mode = $dryRun ? '<comment>[DRY RUN]</comment>' : '<info>[EXECUTED]</info>';
        $this->line("{$mode} Inserts: ".count($toInsert).' | Updates: '.count($toUpdate).' | Warnings: '.count($warnings));

        if ($dryRun) {
            if (! $this->getOutput()->isVerbose()) {
                $this->line('         Run with -v to see all rows, or --execute to write.');
            }

            return Command::SUCCESS;
        }

        if ($warnings && ! $this->confirm('There are '.count($warnings).' warnings. Continue anyway?', true)) {
            return Command::FAILURE;
        }

        DB::transaction(function () use ($brand, $toInsert, $toUpdate) {
            foreach ($toInsert as $item) {
                Sku::create($item['attrs']);
            }

            foreach ($toUpdate as $item) {
                $item['sku']->update(array_map(fn ($c) => $c['to'], $item['changes']));
            }

            $this->linkIdenticalSkus($brand);
        });

        $this->info('Done. Inserted '.count($toInsert).', updated '.count($toUpdate).'.');

        return Command::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $attrs
     * @return array<string, array{from: mixed, to: mixed}>
     */
    private function diffSku(Sku $existing, array $attrs): array
    {
        $changes = [];
        foreach (['name', 'color_id', 'balloon_size_id', 'material_id', 'upc', 'default_count_per_bag'] as $field) {
            $from = $existing->getAttribute($field);
            $to = $attrs[$field];
            if ($field === 'default_count_per_bag') {
                $from = $from === null ? null : (int) $from;
                $to = $to === null ? null : (int) $to;
            }
            if ($from !== $to) {
                $changes[$field] = ['from' => $from, 'to' => $to];
            }
        }

        return $changes;
    }

    /**
     * Group Funsational SKUs by (balloon_size_id, color_id); link every group of
     * >1 (different pack counts of the same balloon) via the identical_skus pivot
     * in both directions. Idempotent.
     */
    private function linkIdenticalSkus(Brand $brand): void
    {
        $skus = Sku::where('brand_id', $brand->id)
            ->whereNotNull('balloon_size_id')
            ->whereNotNull('color_id')
            ->get(['id', 'balloon_size_id', 'color_id']);

        $groups = $skus->groupBy(fn (Sku $s) => $s->balloon_size_id.'|'.$s->color_id);

        foreach ($groups as $group) {
            if ($group->count() < 2) {
                continue;
            }
            $ids = $group->pluck('id')->all();
            foreach ($group as $sku) {
                $sku->identicalSkus()->syncWithoutDetaching(array_diff($ids, [$sku->id]));
            }
        }
    }
}
