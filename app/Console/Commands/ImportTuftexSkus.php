<?php

namespace App\Console\Commands;

use App\Models\BalloonSize;
use App\Models\Brand;
use App\Models\Color;
use App\Models\Sku;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportTuftexSkus extends Command
{
    protected $signature = 'catalog:import-tuftex
                            {--execute : Write to the database (omit for dry-run)}';

    protected $description = 'Import TufTex SKUs from intake/tuftex_normalized.json';

    public function handle(): int
    {
        $dryRun = ! $this->option('execute');
        $jsonPath = base_path('intake/tuftex_normalized.json');

        if (! file_exists($jsonPath)) {
            $this->error("File not found: {$jsonPath}");
            $this->line('Run: python3 intake/tuftex_normalize.py');

            return Command::FAILURE;
        }

        /** @var array<int, array<string, mixed>> $rows */
        $rows = json_decode(file_get_contents($jsonPath), true);

        $brand = Brand::where('name', 'TufTex')->firstOrFail();

        $balloonSizes = BalloonSize::where('brand_id', $brand->id)
            ->get()
            ->keyBy('name');

        $colors = Color::where('brand_id', $brand->id)
            ->get()
            ->keyBy(fn (Color $c) => strtolower($c->name));

        $assortmentColor = $colors->get('assortment');
        if (! $assortmentColor) {
            $this->error('TufTex "Assortment" color not found in database. Please create it first.');

            return Command::FAILURE;
        }

        $existingByMfgNo = Sku::where('brand_id', $brand->id)
            ->whereNotNull('mfg_no')
            ->get()
            ->keyBy('mfg_no');

        $existingByUpc = Sku::where('brand_id', $brand->id)
            ->whereNotNull('upc')
            ->get()
            ->keyBy('upc');

        $toInsert = [];
        $toUpdate = [];
        $assorteds = [];
        $warnings = [];

        foreach ($rows as $row) {
            $size = $balloonSizes->get($row['size_label']);
            if (! $size) {
                $warnings[] = "No balloon_size '{$row['size_label']}': {$row['description']}";

                continue;
            }

            $isAssorted = $row['canonical_color'] === null;

            if ($isAssorted) {
                $colorId = $assortmentColor->id;
                $colorName = $this->cleanAssortedName($row['raw_color']);
            } else {
                $color = $colors->get(strtolower($row['canonical_color']));
                if (! $color) {
                    $warnings[] = "Color not found '{$row['canonical_color']}': {$row['description']}";

                    continue;
                }
                $colorId = $color->id;
                $colorName = $color->name;
            }

            $existing = $existingByMfgNo->get($row['mfg_no'])
                ?? $existingByUpc->get($row['upc']);

            if ($existing) {
                $changes = [];
                if (! $existing->mfg_no && $row['mfg_no']) {
                    $changes['mfg_no'] = ['from' => null, 'to' => $row['mfg_no']];
                }
                if ($existing->upc !== $row['upc']) {
                    $changes['upc'] = ['from' => $existing->upc, 'to' => $row['upc']];
                }
                if ((int) $existing->default_count_per_bag !== (int) $row['bag_count']) {
                    $changes['default_count_per_bag'] = ['from' => $existing->default_count_per_bag, 'to' => $row['bag_count']];
                }
                if (! empty($changes)) {
                    $toUpdate[] = [
                        'sku' => $existing,
                        'changes' => $changes,
                        'row' => $row,
                    ];
                }
            } else {
                $attrs = [
                    'name' => $colorName,
                    'brand_id' => $brand->id,
                    'color_id' => $colorId,
                    'balloon_size_id' => $size->id,
                    'material_id' => $size->material_id,
                    'mfg_no' => $row['mfg_no'],
                    'upc' => $row['upc'],
                    'default_count_per_bag' => $row['bag_count'],
                    'is_active' => true,
                ];

                if ($isAssorted) {
                    $assorteds[] = ['attrs' => $attrs, 'row' => $row];
                } else {
                    $toInsert[] = ['attrs' => $attrs, 'row' => $row];
                }
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
            $this->line('<info>INSERTS (solid colors):</info>');
            foreach ($toInsert as $item) {
                $r = $item['row'];
                $this->line("  + [{$r['mfg_no']}] {$r['description']} → name={$item['attrs']['name']}, size={$r['size_label']}, upc={$r['upc']}, count={$r['bag_count']}");
            }

            $this->newLine();
            $this->line('<info>INSERTS (assorted — no color_id):</info>');
            foreach ($assorteds as $item) {
                $r = $item['row'];
                $this->line("  + [{$r['mfg_no']}] {$r['description']} → name={$item['attrs']['name']}, size={$r['size_label']}");
            }

            if ($toUpdate) {
                $this->newLine();
                $this->line('<info>UPDATES:</info>');
                foreach ($toUpdate as $item) {
                    $r = $item['row'];
                    $this->line("  ~ [{$r['mfg_no']}] {$r['description']} → ".json_encode($item['changes']));
                }
            }
        }

        $this->newLine();
        $mode = $dryRun ? '<comment>[DRY RUN]</comment>' : '<info>[EXECUTED]</info>';
        $this->line("{$mode} Solid inserts: ".count($toInsert).
            ' | Assorted inserts: '.count($assorteds).
            ' | Updates: '.count($toUpdate).
            ' | Warnings: '.count($warnings));

        if ($dryRun && ! $this->getOutput()->isVerbose()) {
            $this->line('         Run with -v to see all rows, or --execute to write.');
        }

        // ── Execute ──────────────────────────────────────────────────────────
        if (! $dryRun) {
            if ($warnings && ! $this->confirm('There are '.count($warnings).' warnings. Continue anyway?')) {
                return Command::FAILURE;
            }

            DB::transaction(function () use ($toInsert, $assorteds, $toUpdate) {
                foreach ([...$toInsert, ...$assorteds] as $item) {
                    Sku::create($item['attrs']);
                }

                foreach ($toUpdate as $item) {
                    $item['sku']->update(array_map(fn ($c) => $c['to'], $item['changes']));
                }
            });

            $this->info('Done. Inserted '.(count($toInsert) + count($assorteds)).', updated '.count($toUpdate).'.');
        }

        return Command::SUCCESS;
    }

    private function cleanAssortedName(string $raw): string
    {
        return str_ireplace(
            ['Asst W/White', 'Asst w/ White', 'Asst'],
            ['Assorted w/ White', 'Assorted w/ White', 'Assorted'],
            $raw,
        );
    }
}
