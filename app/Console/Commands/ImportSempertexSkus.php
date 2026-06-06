<?php

namespace App\Console\Commands;

use App\Models\BalloonSize;
use App\Models\Brand;
use App\Models\Color;
use App\Models\PackagingType;
use App\Models\Sku;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportSempertexSkus extends Command
{
    protected $signature = 'catalog:import-sempertex
                            {--execute : Write to the database (omit for dry-run)}
                            {--path= : Override path to the normalized JSON file}';

    protected $description = 'Import Sempertex SKUs from intake/sempertex_normalized.json';

    public function handle(): int
    {
        $dryRun = ! $this->option('execute');
        $jsonPath = $this->option('path') ?: base_path('intake/sempertex_normalized.json');

        if (! file_exists($jsonPath)) {
            $this->error("File not found: {$jsonPath}");
            $this->line('Run: python3 intake/sempertex_normalize.py');

            return Command::FAILURE;
        }

        /** @var array<int, array<string, mixed>> $rows */
        $rows = json_decode(file_get_contents($jsonPath), true);

        if (! is_array($rows) || $rows === []) {
            $this->error("File is empty or invalid JSON: {$jsonPath}");

            return Command::FAILURE;
        }

        [$rows, $dedupeWarnings] = $this->dedupeByWarehouseSku($rows);

        $brand = Brand::where('name', 'Sempertex')->firstOrFail();

        $balloonSizes = BalloonSize::where('brand_id', $brand->id)->get()->keyBy('name');
        $colors = Color::where('brand_id', $brand->id)->get()->keyBy('name');
        $packagingTypes = PackagingType::all()->keyBy('name');

        $existingByWarehouseSku = Sku::where('brand_id', $brand->id)
            ->whereNotNull('warehouse_sku')
            ->get()
            ->keyBy('warehouse_sku');

        $toInsert = [];
        $toUpdate = [];
        $warnings = $dedupeWarnings;

        foreach ($rows as $row) {
            $size = $balloonSizes->get($row['size_resolved']);
            if (! $size) {
                $warnings[] = "No balloon_size '{$row['size_resolved']}': {$row['raw_name']}";

                continue;
            }

            $color = $colors->get($row['color_resolved']);
            if (! $color) {
                $warnings[] = "Color not found '{$row['color_resolved']}': {$row['raw_name']}";

                continue;
            }

            $packaging = $packagingTypes->get($row['packaging']);
            if (! $packaging) {
                $warnings[] = "Packaging type not found '{$row['packaging']}': {$row['raw_name']}";

                continue;
            }

            $attrs = [
                'name' => $row['raw_name'],
                'brand_id' => $brand->id,
                'color_id' => $color->id,
                'balloon_size_id' => $size->id,
                'material_id' => $size->material_id,
                'packaging_id' => $packaging->id,
                'warehouse_sku' => $row['warehouse_sku'],
                'default_count_per_bag' => $row['count_per_bag'],
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

        // ── Report ──────────────────────────────────────────────────────────
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
                $this->line("  + [{$r['warehouse_sku']}] {$r['raw_name']} → size={$r['size_resolved']}, color={$r['color_resolved']}, pkg={$r['packaging']}");
            }

            if ($toUpdate) {
                $this->newLine();
                $this->line('<info>UPDATES:</info>');
                foreach ($toUpdate as $item) {
                    $r = $item['row'];
                    $this->line("  ~ [{$r['warehouse_sku']}] {$r['raw_name']} → ".json_encode($item['changes']));
                }
            }
        }

        $this->newLine();
        $mode = $dryRun ? '<comment>[DRY RUN]</comment>' : '<info>[EXECUTED]</info>';
        $this->line("{$mode} Inserts: ".count($toInsert).' | Updates: '.count($toUpdate).' | Warnings: '.count($warnings));

        if ($dryRun && ! $this->getOutput()->isVerbose()) {
            $this->line('         Run with -v to see all rows, or --execute to write.');

            return Command::SUCCESS;
        }

        if ($dryRun) {
            return Command::SUCCESS;
        }

        // ── Execute ─────────────────────────────────────────────────────────
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
     * Drop rows that repeat a warehouse_sku already seen earlier in the input.
     * Without this, two rows sharing a warehouse_sku both miss the existing-by-
     * warehouse_sku lookup (loaded once before the loop) and both INSERT —
     * producing duplicate Sku rows for what should be one physical product.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, string>}
     */
    private function dedupeByWarehouseSku(array $rows): array
    {
        $seen = [];
        $deduped = [];
        $warnings = [];

        foreach ($rows as $row) {
            $sku = $row['warehouse_sku'] ?? null;
            if (! $sku) {
                $deduped[] = $row;

                continue;
            }
            if (isset($seen[$sku])) {
                $warnings[] = "Duplicate warehouse_sku '{$sku}' in input — keeping first '{$seen[$sku]}', skipping '{$row['raw_name']}'";

                continue;
            }
            $seen[$sku] = $row['raw_name'];
            $deduped[] = $row;
        }

        return [$deduped, $warnings];
    }

    /**
     * @return array<string, array{from: mixed, to: mixed}>
     */
    private function diffSku(Sku $existing, array $attrs): array
    {
        $changes = [];
        foreach (['name', 'color_id', 'balloon_size_id', 'material_id', 'packaging_id', 'default_count_per_bag'] as $field) {
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
     * Group every Sempertex SKU by (balloon_size_id, color_id). For every
     * group with more than one row, link them via the identical_skus pivot
     * in both directions so packaging variants (PAQ X 12 vs PAQ X 50) of the
     * same physical balloon cross-reference each other.
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
                $siblings = array_diff($ids, [$sku->id]);
                $sku->identicalSkus()->syncWithoutDetaching($siblings);
            }
        }
    }
}
