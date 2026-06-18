<?php

namespace App\Console\Commands;

use App\Services\OnboardingSeedResolver;
use Illuminate\Console\Command;

class OnboardingSeedList extends Command
{
    protected $signature = 'onboarding:seed-list
                            {role? : Role to resolve (twister, decorator, retailer, ...); omit for all}
                            {--check : Validate & preview only — currently the only mode}
                            {--brands= : Comma-separated brand names to limit to}
                            {--path= : Override the spec directory (defaults to database/data/onboarding/seed_lists)}';

    protected $description = 'Resolve onboarding seed-list specs against the catalog and report matches/gaps.';

    public function __construct(private readonly OnboardingSeedResolver $resolver)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $directory = $this->option('path') ?: $this->resolver->defaultDirectory();

        $onlyBrands = $this->option('brands')
            ? array_map('trim', explode(',', (string) $this->option('brands')))
            : null;

        $role = $this->argument('role');

        if ($role !== null) {
            $spec = $this->resolver->findSpecForRole($role, $directory);

            if ($spec === null) {
                $this->error("No seed-list spec found for role '{$role}'.");

                return Command::FAILURE;
            }

            $specs = [$spec];
        } else {
            $specs = $this->resolver->loadSpecs($directory);
        }

        if ($specs === []) {
            $this->error("No seed-list specs found in {$directory}.");

            return Command::FAILURE;
        }

        foreach ($specs as $spec) {
            $this->reportSpec($spec, $onlyBrands);
        }

        return Command::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $spec
     * @param  array<int, string>|null  $onlyBrands
     */
    private function reportSpec(array $spec, ?array $onlyBrands): void
    {
        $label = $spec['label'] ?? ($spec['_file'] ?? 'spec');
        $roles = implode(', ', $this->resolver->rolesFor($spec));

        $rows = $this->resolver->resolve($spec, $onlyBrands);

        $this->newLine();
        $this->line("<options=bold>{$label}</> <fg=gray>(roles: {$roles})</>");

        // Per-brand tallies.
        $byBrand = [];
        foreach ($rows as $row) {
            $byBrand[$row['brand']][$row['status']] = ($byBrand[$row['brand']][$row['status']] ?? 0) + 1;
        }

        $summary = [];
        foreach ($byBrand as $brand => $statuses) {
            $matched = $statuses['matched'] ?? 0;
            $total = array_sum($statuses);
            $gaps = ($statuses['no_match'] ?? 0) + ($statuses['brand_missing'] ?? 0);
            $summary[] = [$brand, "{$matched}/{$total}", $gaps ?: '—'];
        }
        $this->table(['Brand', 'Matched', 'Gaps'], $summary);

        // Problem rows (always shown) — these are what you act on.
        $problems = array_filter($rows, fn ($r) => $r['status'] !== 'matched' || $r['count_fallback']);

        if ($problems !== []) {
            $detail = [];
            foreach ($problems as $row) {
                $detail[] = [
                    $this->statusIcon($row),
                    $row['brand'],
                    trim("{$row['size']} {$row['shape']} {$row['color']}"),
                    $this->statusNote($row),
                ];
            }
            $this->table(['', 'Brand', 'Item', 'Note'], $detail);
        }

        // Full matched list only with -v.
        if ($this->getOutput()->isVerbose()) {
            $matchedRows = array_filter($rows, fn ($r) => $r['status'] === 'matched');
            $detail = [];
            foreach ($matchedRows as $row) {
                $detail[] = [
                    $row['brand'],
                    trim("{$row['size']} {$row['shape']} {$row['color']}"),
                    "{$row['bags']} bag(s) @ {$row['count_per_bag']}ct",
                    $row['sku_name'],
                ];
            }
            $this->newLine();
            $this->line('<info>Matched:</info>');
            $this->table(['Brand', 'Item', 'Seed', 'Resolved SKU'], $detail);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function statusIcon(array $row): string
    {
        return match (true) {
            $row['status'] === 'brand_missing' => '<fg=gray>—</>',
            $row['status'] === 'no_match' => '<fg=red>✗</>',
            $row['count_fallback'] => '<fg=yellow>⚠</>',
            default => '<fg=green>✓</>',
        };
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function statusNote(array $row): string
    {
        return match (true) {
            $row['status'] === 'brand_missing' => 'brand not in catalog (pending)',
            $row['status'] === 'no_match' => 'no matching SKU',
            $row['count_fallback'] => "no preferred count; using {$row['count_per_bag']}ct",
            default => '',
        };
    }
}
