<?php

namespace App\Console\Commands\Catalog;

use App\Models\BalloonSize;
use App\Models\Brand;
use App\Models\Color;
use App\Models\PackagingType;
use App\Models\Sku;
use App\Support\Gtin;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BackfillSempertexUpcs extends Command
{
    protected $signature = 'catalog:backfill-sempertex-upcs
                            {--apply : Write resolved UPCs to the database (omit for dry-run)}
                            {--source= : Path to sempertex_upcs_raw.json (default: intake/sempertex_upcs_raw.json)}';

    protected $description = 'Match scraped Sempertex UPC data to skus and optionally back-fill the upc column.';

    /**
     * Map Larock/Betallic color slugs to canonical DB color slugs where they differ.
     *
     * @var array<string, string>
     */
    private const COLOR_SLUG_ALIASES = [
        // Larock uses US "gray" spelling; DB uses UK "grey"
        'deluxe-gray' => 'deluxe-grey',
        'deluxe-urban-gray' => 'deluxe-urban-grey',
        'silk-midnight-gray' => 'silk-midnight-grey',
        // Larock appends "-brown" to some Deluxe names
        'deluxe-mocha-brown' => 'deluxe-mocha',
        'deluxe-chocolate-brown' => 'deluxe-chocolate',
        // Larock word-order differs
        'deluxe-blush-peach' => 'deluxe-peach-blush',
        // Larock appends "-green" to Key Lime
        'deluxe-key-lime-green' => 'deluxe-key-lime',
        // Larock adds "-blush" to Pastel Matte Nude
        'pastel-matte-nude-blush' => 'pastel-matte-nude',
        // Larock "Laurel Green" vs DB "Green Tea"
        'pastel-dusk-laurel-green' => 'pastel-dusk-green-tea',
        'pastel-dusk-dark-green' => 'pastel-dusk-green-tea',
    ];

    public function handle(): int
    {
        $sourcePath = $this->option('source') ?: base_path('intake/sempertex_upcs_raw.json');

        if (! file_exists($sourcePath)) {
            $this->error("Source file not found: {$sourcePath}");
            $this->line('Run: python3 intake/sempertex_upc_fetch.py');

            return self::FAILURE;
        }

        /** @var array<int, array<string, mixed>> $entries */
        $entries = json_decode(file_get_contents($sourcePath), true);

        if (! is_array($entries) || $entries === []) {
            $this->error('Source file is empty or invalid JSON.');

            return self::FAILURE;
        }

        $shouldApply = (bool) $this->option('apply');

        $brand = Brand::where('name', 'Sempertex')->firstOrFail();

        // Keyed collections for O(1) lookups
        $colors = Color::where('brand_id', $brand->id)->get()->keyBy(
            fn (Color $c) => $this->colorToSlug($c->name)
        );
        $balloonSizes = BalloonSize::where('brand_id', $brand->id)
            ->get()
            ->keyBy('name');
        $loosePackaging = PackagingType::where('name', 'Loose')->firstOrFail();

        // All Sempertex SKUs keyed by "{balloon_size_id}|{color_id}|{count}"
        $skus = Sku::where('brand_id', $brand->id)
            ->whereNotNull('balloon_size_id')
            ->whereNotNull('color_id')
            ->where('packaging_id', $loosePackaging->id)
            ->get(['id', 'balloon_size_id', 'color_id', 'default_count_per_bag', 'upc'])
            ->keyBy(fn (Sku $s) => "{$s->balloon_size_id}|{$s->color_id}|{$s->default_count_per_bag}");

        // ── Categorise every scraped entry ───────────────────────────────────
        $bySize = [];   // size_name → ['matched' => [], 'no_color' => [], 'no_sku' => [], 'invalid_upc' => [], 'already_set' => []]

        $colorsSortedByNameLen = Color::where('brand_id', $brand->id)
            ->get()
            ->sortByDesc(fn (Color $c) => strlen($c->name));

        foreach ($entries as $entry) {
            $sizeName = (string) ($entry['size'] ?? '');
            $upc = (string) ($entry['upc'] ?? '');
            $count = (int) ($entry['count'] ?? 0);

            $bySize[$sizeName] ??= [
                'matched' => [],
                'no_color' => [],
                'no_sku' => [],
                'invalid_upc' => [],
                'already_set' => [],
            ];

            // 1. Validate UPC
            if (! Gtin::isValidCheckDigit($upc)) {
                $bySize[$sizeName]['invalid_upc'][] = [
                    'raw_title' => $entry['raw_title'] ?? '',
                    'upc' => $upc,
                    'source' => $entry['source'] ?? '',
                ];

                continue;
            }

            // 2. Resolve balloon size
            $balloonSize = $balloonSizes->get($sizeName);
            if (! $balloonSize) {
                $bySize[$sizeName]['no_sku'][] = [
                    'raw_title' => $entry['raw_title'] ?? '',
                    'upc' => $upc,
                    'reason' => "unknown size '{$sizeName}'",
                ];

                continue;
            }

            // 3. Resolve color
            $color = $this->resolveColor($entry, $colors, $colorsSortedByNameLen);
            if (! $color) {
                $bySize[$sizeName]['no_color'][] = [
                    'raw_title' => $entry['raw_title'] ?? '',
                    'color_slug' => $entry['color_slug'] ?? null,
                    'upc' => $upc,
                    'source' => $entry['source'] ?? '',
                ];

                continue;
            }

            // 4. Find matching SKU
            $skuKey = "{$balloonSize->id}|{$color->id}|{$count}";
            $sku = $skus->get($skuKey);
            if (! $sku) {
                $bySize[$sizeName]['no_sku'][] = [
                    'raw_title' => $entry['raw_title'] ?? '',
                    'color' => $color->name,
                    'count' => $count,
                    'upc' => $upc,
                    'reason' => 'no SKU for this size/color/count',
                ];

                continue;
            }

            // 5. Check existing UPC
            if ($sku->upc !== null) {
                $bySize[$sizeName]['already_set'][] = [
                    'color' => $color->name,
                    'sku_id' => $sku->id,
                    'existing' => $sku->upc,
                    'incoming' => $upc,
                    'same' => $sku->upc === $upc,
                ];

                continue;
            }

            $bySize[$sizeName]['matched'][] = [
                'color' => $color->name,
                'sku_id' => $sku->id,
                'upc' => $upc,
                'source' => $entry['source'] ?? '',
            ];
        }

        // ── Render report ────────────────────────────────────────────────────
        $totalMatched = 0;
        foreach ($bySize as $sizeName => $groups) {
            $matchedCount = count($groups['matched']);
            $totalMatched += $matchedCount;

            $this->line("== {$sizeName} ==");
            $this->line(sprintf(
                '  matched=%d  already-set=%d  no-color=%d  no-sku=%d  invalid-upc=%d',
                $matchedCount,
                count($groups['already_set']),
                count($groups['no_color']),
                count($groups['no_sku']),
                count($groups['invalid_upc']),
            ));

            if ($matchedCount > 0) {
                $this->newLine();
                $action = $shouldApply ? 'applied' : 'will apply';
                $this->line("  -- {$action} --");
                foreach ($groups['matched'] as $row) {
                    $this->line("    [{$row['color']}] → {$row['upc']}  ({$row['source']})");
                }
            }

            if ($groups['already_set'] !== []) {
                $this->newLine();
                $this->line('  -- already set --');
                foreach ($groups['already_set'] as $row) {
                    $same = $row['same'] ? '(same)' : '⚠ CONFLICT';
                    $this->line("    [{$row['color']}] existing={$row['existing']} incoming={$row['incoming']} {$same}");
                }
            }

            if ($groups['no_color'] !== []) {
                $this->newLine();
                $this->line('  -- no color match --');
                foreach ($groups['no_color'] as $row) {
                    $slug = $row['color_slug'] ? "slug={$row['color_slug']}" : "title=\"{$row['raw_title']}\"";
                    $this->line("    {$slug}  upc={$row['upc']}");
                }
            }

            if ($groups['no_sku'] !== []) {
                $this->newLine();
                $this->line('  -- no SKU match --');
                foreach ($groups['no_sku'] as $row) {
                    $label = isset($row['color'])
                        ? "[{$row['color']}] {$row['count']}CT"
                        : $row['raw_title'];
                    $this->line("    {$label} — {$row['reason']}");
                }
            }

            if ($groups['invalid_upc'] !== []) {
                $this->newLine();
                $this->line('  -- invalid UPC --');
                foreach ($groups['invalid_upc'] as $row) {
                    $this->line("    \"{$row['raw_title']}\" upc={$row['upc']}");
                }
            }

            $this->newLine();
        }

        // ── Summary ──────────────────────────────────────────────────────────
        $totalNoColor = array_sum(array_map(fn ($g) => count($g['no_color']), $bySize));
        $totalNoSku = array_sum(array_map(fn ($g) => count($g['no_sku']), $bySize));
        $totalAlreadySet = array_sum(array_map(fn ($g) => count($g['already_set']), $bySize));
        $totalInvalid = array_sum(array_map(fn ($g) => count($g['invalid_upc']), $bySize));

        $this->line('== summary ==');
        $this->line(sprintf(
            '  entries=%d  matched=%d  already-set=%d  no-color=%d  no-sku=%d  invalid-upc=%d',
            count($entries),
            $totalMatched,
            $totalAlreadySet,
            $totalNoColor,
            $totalNoSku,
            $totalInvalid,
        ));

        if (! $shouldApply) {
            if ($totalMatched > 0) {
                $this->newLine();
                $this->warn("Re-run with --apply to write {$totalMatched} UPC(s) to the database.");
            }

            return self::SUCCESS;
        }

        // ── Apply ─────────────────────────────────────────────────────────────
        if ($totalMatched === 0) {
            $this->info('Nothing to apply.');

            return self::SUCCESS;
        }

        $written = 0;
        DB::transaction(function () use ($bySize, &$written) {
            foreach ($bySize as $groups) {
                foreach ($groups['matched'] as $row) {
                    DB::table('skus')
                        ->where('id', $row['sku_id'])
                        ->update(['upc' => $row['upc'], 'updated_at' => now()]);
                    $written++;
                }
            }
        });

        $this->info("Applied {$written} UPC(s).");

        return self::SUCCESS;
    }

    // ── Color resolution ───────────────────────────────────────────────────

    /**
     * Resolve an entry's color against the Sempertex color collection.
     *
     * Larock entries carry a pre-parsed `color_slug` extracted from the product
     * URL.  Betallic US entries carry only a `raw_title`; we substring-match
     * against all DB color names (longest first to avoid false partials).
     *
     * @param  Collection<string, Color>  $colorsBySlug
     * @param  Collection<int, Color>  $colorsSortedByNameLen
     */
    private function resolveColor(
        array $entry,
        Collection $colorsBySlug,
        Collection $colorsSortedByNameLen,
    ): ?Color {
        $rawSlug = (string) ($entry['color_slug'] ?? '');

        if ($rawSlug !== '') {
            // Strip Larock-specific suffixes that trail the actual color slug.
            // e.g. "pastel-matte-blue-link-o-loon-betallatex-5" → "pastel-matte-blue"
            //      "deluxe-imperial-red-betallatex"             → "deluxe-imperial-red"
            $slug = (string) preg_replace('/-link-o-loon-betallatex(-\d+)?$/', '', $rawSlug);
            $slug = (string) preg_replace('/-betallatex$/', '', $slug);
            $slug = self::COLOR_SLUG_ALIASES[$slug] ?? $slug;

            return $colorsBySlug->get($slug);
        }

        // Betallic US: substring-match raw_title against every color name.
        $normalTitle = $this->normalizeForMatch($entry['raw_title'] ?? '');

        foreach ($colorsSortedByNameLen as $color) {
            if (str_contains($normalTitle, $this->normalizeForMatch($color->name))) {
                return $color;
            }
        }

        return null;
    }

    /**
     * Convert a DB color name to the slug form used in Larock URL paths.
     * "Fashion Robin's Egg Blue" → "fashion-robins-egg-blue"
     */
    private function colorToSlug(string $name): string
    {
        $slug = strtolower($name);
        $slug = str_replace("'", '', $slug);
        $slug = (string) preg_replace('/[^a-z0-9]+/', '-', $slug);

        return trim($slug, '-');
    }

    /**
     * Collapse a string to lowercase letters, digits, and single spaces — used
     * for fuzzy substring matching of Betallic US product titles.
     */
    private function normalizeForMatch(string $s): string
    {
        $s = strtolower($s);
        $s = (string) preg_replace('/[^a-z0-9]+/', ' ', $s);

        return trim((string) preg_replace('/\s+/', ' ', $s));
    }
}
