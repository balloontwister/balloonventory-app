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

class ImportQualatexSkus extends Command
{
    protected $signature = 'catalog:import-qualatex
                            {--execute : Write to the database (omit for dry-run)}
                            {--path= : Override path to the normalized JSON file}';

    protected $description = 'Import Qualatex SKUs from intake/qualatex_normalized.json (sourced from the legacy From_Larry inventory)';

    public function handle(): int
    {
        $dryRun = ! $this->option('execute');
        $jsonPath = $this->option('path') ?: base_path('intake/qualatex_normalized.json');

        if (! file_exists($jsonPath)) {
            $this->error("File not found: {$jsonPath}");
            $this->line('Run: python3 intake/qualatex_normalize.py');

            return Command::FAILURE;
        }

        /** @var array<int, array<string, mixed>> $rows */
        $rows = json_decode(file_get_contents($jsonPath), true);

        if (! is_array($rows) || $rows === []) {
            $this->error('Source file is empty or invalid JSON.');

            return Command::FAILURE;
        }

        $brand = Brand::where('name', 'Qualatex')->firstOrFail();

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
        $barcodeConflicts = [];
        $seenUpc = [];

        foreach ($rows as $row) {
            $warehouseSku = (string) ($row['warehouse_sku'] ?? '');
            if ($warehouseSku === '') {
                $warnings[] = "Missing warehouse_sku: {$row['raw_name']}";

                continue;
            }

            $size = $balloonSizes->get($row['balloon_size']);
            if (! $size) {
                $warnings[] = "No balloon_size '{$row['balloon_size']}': {$row['raw_name']}";

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
                'warehouse_sku' => $warehouseSku,
                'default_count_per_bag' => $row['count_per_bag'],
                'is_active' => true,
            ];

            // Larry stored UPC-A without the leading number-system 0 (11 digits);
            // the normalizer already canonicalized it to 12. Only attach a valid
            // GTIN; anything else is reported rather than silently stored.
            $barcode = preg_replace('/[^0-9]/', '', (string) ($row['upc'] ?? ''));

            if (strlen($barcode) === 12 && Gtin::isValidCheckDigit($barcode)) {
                $attrs['upc'] = $barcode;
            } elseif ($barcode !== '') {
                $warnings[] = "Invalid UPC '{$barcode}' for '{$warehouseSku}'";
            }

            // A UPC must map to exactly one SKU. Catch duplicates within the feed
            // before they hit the DB's soft-delete-aware unique index.
            if (isset($attrs['upc'])) {
                if (isset($seenUpc[$attrs['upc']])) {
                    $barcodeConflicts[] = "Duplicate UPC {$attrs['upc']} in feed ('{$warehouseSku}' and '{$seenUpc[$attrs['upc']]}')";
                    unset($attrs['upc']);
                } else {
                    $seenUpc[$attrs['upc']] = $warehouseSku;
                }
            }

            $existing = $existingByWarehouseSku->get($warehouseSku);

            if ($existing) {
                // No-clobber: never overwrite an existing barcode.
                if (isset($attrs['upc']) && $existing->upc && $existing->upc !== $attrs['upc']) {
                    $barcodeConflicts[] = "UPC conflict for '{$warehouseSku}': existing={$existing->upc} incoming={$attrs['upc']}";
                }
                if ($existing->upc) {
                    unset($attrs['upc']);
                }

                $changes = $this->diffSku($existing, $attrs);
                if ($changes) {
                    $toUpdate[] = ['sku' => $existing, 'changes' => $changes];
                }
            } else {
                $toInsert[] = ['attrs' => $attrs];
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
                $this->warn('  ... and '.(count($warnings) - 20).' more');
            }
        }

        if ($barcodeConflicts) {
            $this->newLine();
            $this->warn('BARCODE CONFLICTS ('.count($barcodeConflicts).'):');
            foreach (array_slice($barcodeConflicts, 0, 10) as $c) {
                $this->warn("  ⚠ {$c}");
            }
        }

        $this->newLine();
        $barcodeCount = count(array_filter($toInsert, fn ($item) => isset($item['attrs']['upc'])));
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
     * @param  array<string, mixed>  $attrs
     * @return array<string, array{from: mixed, to: mixed}>
     */
    private function diffSku(Sku $existing, array $attrs): array
    {
        $changes = [];
        foreach (['name', 'color_id', 'balloon_size_id', 'material_id', 'packaging_id', 'default_count_per_bag', 'upc'] as $field) {
            if (! array_key_exists($field, $attrs)) {
                continue;
            }
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
     * Group every Qualatex SKU by (balloon_size_id, color_id) and link each
     * group of >1 via the identical_skus pivot in both directions, so pack-size
     * variants of the same physical balloon cross-reference each other.
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
