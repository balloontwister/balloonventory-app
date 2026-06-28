<?php

namespace App\Console\Commands;

use App\Models\BalloonSize;
use App\Models\Brand;
use App\Models\Material;
use App\Models\Sku;
use App\Models\Theme;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportTuftexFoils extends Command
{
    protected $signature = 'catalog:import-tuftex-foils
                            {--execute : Write to the database (omit for dry-run)}
                            {--path= : Override the normalized JSON path}';

    protected $description = 'Import TufTex foil SKUs from intake/tuftex/tuftex_foils_normalized.json';

    public function handle(): int
    {
        $dryRun = ! $this->option('execute');
        $jsonPath = $this->option('path') ?: base_path('intake/tuftex/tuftex_foils_normalized.json');

        if (! file_exists($jsonPath)) {
            $this->error("File not found: {$jsonPath}");
            $this->line('Run: python3 intake/tuftex/build_tuftex_foils_sheet.py');

            return Command::FAILURE;
        }

        /** @var array<int, array<string, mixed>> $rows */
        $rows = json_decode(file_get_contents($jsonPath), true);

        $brand = Brand::where('name', 'TufTex')->firstOrFail();
        $foil = Material::where('name', 'Foil')->firstOrFail();

        $balloonSizes = BalloonSize::where('brand_id', $brand->id)
            ->where('material_id', $foil->id)
            ->get()
            ->keyBy('name');

        $themes = Theme::get()->keyBy(fn (Theme $t) => strtolower($t->name));

        $existingByName = Sku::where('brand_id', $brand->id)
            ->where('material_id', $foil->id)
            ->get()
            ->keyBy(fn (Sku $s) => strtolower($s->name));

        $toInsert = [];
        $toUpdate = [];
        $warnings = [];

        foreach ($rows as $row) {
            $size = $balloonSizes->get($row['balloon_size_name']);
            if (! $size) {
                $warnings[] = "No balloon_size '{$row['balloon_size_name']}': {$row['name']}";

                continue;
            }

            $theme = $themes->get(strtolower($row['theme']));
            if (! $theme) {
                $warnings[] = "Theme not found '{$row['theme']}': {$row['name']} (will import untagged)";
            }

            $attrs = [
                'name' => $row['name'],
                'brand_id' => $brand->id,
                'material_id' => $foil->id,
                'balloon_size_id' => $size->id,
                'color_id' => null,
                'mfg_no' => $row['mfg_no'],
                'upc' => $row['upc'],
                'default_count_per_bag' => $row['count_per_bag'],
                'is_printed' => true,
                'is_active' => true,
            ];

            $existing = $existingByName->get(strtolower($row['name']));

            if ($existing) {
                $changes = [];
                foreach (['balloon_size_id', 'mfg_no', 'upc', 'default_count_per_bag'] as $field) {
                    if ((string) $existing->{$field} !== (string) $attrs[$field]) {
                        $changes[$field] = ['from' => $existing->{$field}, 'to' => $attrs[$field]];
                    }
                }
                $themeChange = $theme && ! $existing->themes->contains($theme->id);
                if ($changes || $themeChange) {
                    $toUpdate[] = ['sku' => $existing, 'changes' => $changes, 'theme' => $theme, 'row' => $row];
                }
            } else {
                $toInsert[] = ['attrs' => $attrs, 'theme' => $theme, 'row' => $row];
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
                $themeName = $item['theme']?->name ?? '(none)';
                $this->line("  + {$r['name']} → size={$r['balloon_size_name']}, theme={$themeName}, upc=".($r['upc'] ?? 'null').", count={$r['count_per_bag']}");
            }
            if ($toUpdate) {
                $this->newLine();
                $this->line('<info>UPDATES:</info>');
                foreach ($toUpdate as $item) {
                    $this->line("  ~ {$item['row']['name']} → ".json_encode($item['changes']).($item['theme'] && ! $item['sku']->themes->contains($item['theme']->id) ? " +theme={$item['theme']->name}" : ''));
                }
            }
        }

        $this->newLine();
        $mode = $dryRun ? '<comment>[DRY RUN]</comment>' : '<info>[EXECUTED]</info>';
        $this->line("{$mode} Inserts: ".count($toInsert).' | Updates: '.count($toUpdate).' | Warnings: '.count($warnings));

        if ($dryRun && ! $this->getOutput()->isVerbose()) {
            $this->line('         Run with -v to see all rows, or --execute to write.');
        }

        // ── Execute ──────────────────────────────────────────────────────────
        if (! $dryRun) {
            if ($warnings && ! $this->confirm('There are '.count($warnings).' warnings. Continue anyway?')) {
                return Command::FAILURE;
            }

            DB::transaction(function () use ($toInsert, $toUpdate) {
                foreach ($toInsert as $item) {
                    $sku = Sku::create($item['attrs']);
                    if ($item['theme']) {
                        $sku->themes()->syncWithoutDetaching([$item['theme']->id]);
                    }
                }

                foreach ($toUpdate as $item) {
                    if ($item['changes']) {
                        $item['sku']->update(array_map(fn ($c) => $c['to'], $item['changes']));
                    }
                    if ($item['theme']) {
                        $item['sku']->themes()->syncWithoutDetaching([$item['theme']->id]);
                    }
                }
            });

            $this->info('Done. Inserted '.count($toInsert).', updated '.count($toUpdate).'.');
        }

        return Command::SUCCESS;
    }
}
