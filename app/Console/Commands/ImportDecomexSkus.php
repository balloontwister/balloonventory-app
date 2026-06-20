<?php

namespace App\Console\Commands;

use App\Models\BalloonSize;
use App\Models\Brand;
use App\Models\Color;
use App\Models\PackagingType;
use App\Models\Sku;
use App\Support\Gtin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportDecomexSkus extends Command
{
    protected $signature = 'catalog:import-decomex
                            {--execute : Write to the database (omit for dry-run)}
                            {--path= : Override path to the normalized JSON file}';

    protected $description = 'Import Decomex SKUs from intake/decomex_normalized.json';

    public function handle(): int
    {
        $dryRun = ! $this->option('execute');
        $jsonPath = $this->option('path') ?: base_path('intake/decomex_normalized.json');

        if (! file_exists($jsonPath)) {
            $this->error("File not found: {$jsonPath}");
            $this->line('Run: python3 intake/decomex_normalize.py');

            return Command::FAILURE;
        }

        /** @var array<int, array<string, mixed>> $rows */
        $rows = json_decode(file_get_contents($jsonPath), true);

        if (! is_array($rows) || $rows === []) {
            $this->error('Source file is empty or invalid JSON.');

            return Command::FAILURE;
        }

        $brand = Brand::where('name', 'Decomex')->firstOrFail();

        $balloonSizes = BalloonSize::where('brand_id', $brand->id)->get()->keyBy('name');
        $colors = Color::where('brand_id', $brand->id)->get()->keyBy('name');
        $packagingTypes = PackagingType::all()->keyBy('name');

        $existingByWarehouseSku = Sku::where('brand_id', $brand->id)
            ->whereNotNull('warehouse_sku')
            ->get()
            ->keyBy('warehouse_sku');

        $toInsert = [];
        $toUpdate = [];
        $warnings = [];
        $barcodeInserts = [];

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

            // Resolve barcode field
            $barcode = (string) ($row['barcode'] ?? '');
            if (strlen($barcode) === 12 && Gtin::isValidCheckDigit($barcode)) {
                $attrs['upc'] = $barcode;
            } elseif (strlen($barcode) === 13 && Gtin::isValidCheckDigit($barcode)) {
                $attrs['ean'] = $barcode;
            }

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
            foreach (array_slice($warnings, 0, 20) as $w) {
                $this->warn("  ✗ {$w}");
            }
            if (count($warnings) > 20) {
                $this->warn('  ... and '.count($warnings) - 20 .' more (use -v for full list)');
            }
        }

        $this->newLine();
        $barcodeCount = count(array_filter($toInsert, fn ($item) => isset($item['attrs']['upc']) || isset($item['attrs']['ean'])));
        $mode = $dryRun ? '<comment>[DRY RUN]</comment>' : '<info>[EXECUTED]</info>';
        $this->line("{$mode} Inserts: ".count($toInsert).' | Updates: '.count($toUpdate).' | With barcodes: '.$barcodeCount.' | Warnings: '.count($warnings));

        if ($dryRun) {
            $this->line('         Run with --execute to write.');

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
     * Group every Decomex SKU by (balloon_size_id, color_id). For every group
     * with more than one row, link them via the identical_skus pivot in both
     * directions so variants of the same physical balloon (different count or
     * packaging) cross-reference each other.
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
