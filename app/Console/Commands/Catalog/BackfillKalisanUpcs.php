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

class BackfillKalisanUpcs extends Command
{
    protected $signature = 'catalog:backfill-kalisan-upcs
                            {--apply : Write resolved barcodes to the database (omit for dry-run)}
                            {--source= : Path to kalisan_upcs_raw.json (default: intake/kalisan_upcs_raw.json)}';

    protected $description = 'Match scraped Kalisan EAN-13 data to skus and optionally back-fill the ean/upc columns.';

    /**
     * Map Larock colour names to canonical DB colour names where they differ.
     *
     * @var array<string, string>
     */
    private const COLOR_NAME_ALIASES = [
        // Larock uses US "Gray" spelling; DB uses UK "Grey"
        'Standard Gray' => 'Grey',
        'Mirror Space Gray' => 'Mirror Space Grey',
        // Larock appends "Blue" to Navy
        'Standard Navy Blue' => 'Navy',
        // Larock reverses word order vs DB
        'Opaque Satin White Snow' => 'Opaque Satin Snow White',
    ];

    public function handle(): int
    {
        $sourcePath = $this->option('source') ?: base_path('intake/kalisan_upcs_raw.json');

        if (! file_exists($sourcePath)) {
            $this->error("Source file not found: {$sourcePath}");
            $this->line('Run: python3 intake/kalisan_upc_fetch.py');

            return self::FAILURE;
        }

        /** @var array<int, array<string, mixed>> $entries */
        $entries = json_decode(file_get_contents($sourcePath), true);

        if (! is_array($entries) || $entries === []) {
            $this->error('Source file is empty or invalid JSON.');

            return self::FAILURE;
        }

        $shouldApply = (bool) $this->option('apply');

        $brand = Brand::where('name', 'Kalisan')->firstOrFail();

        // Keyed collections for O(1) lookups
        $colorsByTextureAndName = Color::where('brand_id', $brand->id)
            ->get()
            ->groupBy(fn (Color $c) => $this->colorToTextureKey($c))
            ->map(fn (Collection $group) => $group->keyBy('name'));

        $balloonSizes = BalloonSize::where('brand_id', $brand->id)
            ->get()
            ->keyBy('name');

        $packagingTypes = PackagingType::all()->keyBy('name');

        // All Kalisan SKUs keyed by "{balloon_size_id}|{color_id}|{count}|{packaging_id}"
        $skus = Sku::where('brand_id', $brand->id)
            ->whereNotNull('balloon_size_id')
            ->whereNotNull('color_id')
            ->get(['id', 'balloon_size_id', 'color_id', 'default_count_per_bag', 'packaging_id', 'upc', 'ean', 'warehouse_sku'])
            ->keyBy(fn (Sku $s) => "{$s->balloon_size_id}|{$s->color_id}|{$s->default_count_per_bag}|{$s->packaging_id}");

        // Also index by warehouse_sku for entries that carry it
        $skusByWarehouseSku = $skus
            ->filter(fn (Sku $s) => $s->warehouse_sku !== null)
            ->keyBy('warehouse_sku');

        // ── Categorise every scraped entry ───────────────────────────────────
        $bySize = [];   // size_name → ['matched' => [], 'parse_fail' => [], 'no_color' => [], 'no_sku' => [], 'invalid_barcode' => [], 'already_set' => [], 'duplicate_code' => []]

        foreach ($entries as $entry) {
            $barcode = (string) ($entry['barcode'] ?? '');
            $sizeName = (string) ($entry['size'] ?? '');
            $textureName = (string) ($entry['texture'] ?? '');
            $colorName = (string) ($entry['color'] ?? '');
            $count = (int) ($entry['count'] ?? 0);
            $packagingName = (string) ($entry['packaging'] ?? 'Loose');
            $warehouseSku = isset($entry['warehouse_sku']) ? (string) $entry['warehouse_sku'] : null;
            $parseOk = (bool) ($entry['parse_ok'] ?? false);

            $displaySize = $sizeName ?: '(unparsed)';
            $bySize[$displaySize] ??= [
                'matched' => [],
                'parse_fail' => [],
                'no_color' => [],
                'no_sku' => [],
                'invalid_barcode' => [],
                'already_set' => [],
                'duplicate_code' => [],
            ];

            // 0. Title parse failed
            if (! $parseOk || $sizeName === '' || $colorName === '' || $count === 0) {
                $bySize[$displaySize]['parse_fail'][] = [
                    'title' => $entry['title'] ?? '',
                    'barcode' => $barcode,
                ];

                continue;
            }

            // 1. Determine field by length: 13 → ean, 12 → upc
            $field = strlen($barcode) === 12 ? 'upc' : 'ean';

            if (! in_array(strlen($barcode), [12, 13], true)) {
                $bySize[$displaySize]['invalid_barcode'][] = [
                    'title' => $entry['title'] ?? '',
                    'barcode' => $barcode,
                    'reason' => 'barcode length is '.strlen($barcode).' (expected 12 or 13)',
                ];

                continue;
            }

            // 2. Validate GTIN check digit
            if (! Gtin::isValidCheckDigit($barcode)) {
                $bySize[$displaySize]['invalid_barcode'][] = [
                    'title' => $entry['title'] ?? '',
                    'barcode' => $barcode,
                    'reason' => 'invalid GTIN check digit',
                ];

                continue;
            }

            // 3. Resolve balloon size
            $balloonSize = $balloonSizes->get($sizeName);
            if (! $balloonSize) {
                $bySize[$displaySize]['no_sku'][] = [
                    'title' => $entry['title'] ?? '',
                    'barcode' => $barcode,
                    'reason' => "unknown size '{$sizeName}'",
                ];

                continue;
            }

            // 4. Match by warehouse_sku first (if available)
            $sku = null;
            if ($warehouseSku !== null && $warehouseSku !== '') {
                $sku = $skusByWarehouseSku->get($warehouseSku);
            }

            // 5. Match by attributes (size + color + count + packaging)
            if (! $sku) {
                $color = $this->resolveColor($colorName, $textureName, $colorsByTextureAndName);
                if (! $color) {
                    $bySize[$displaySize]['no_color'][] = [
                        'title' => $entry['title'] ?? '',
                        'color' => $colorName,
                        'texture' => $textureName,
                        'barcode' => $barcode,
                    ];

                    continue;
                }

                $packaging = $packagingTypes->get($packagingName);
                if (! $packaging) {
                    $bySize[$displaySize]['no_sku'][] = [
                        'title' => $entry['title'] ?? '',
                        'barcode' => $barcode,
                        'reason' => "unknown packaging '{$packagingName}'",
                    ];

                    continue;
                }

                $skuKey = "{$balloonSize->id}|{$color->id}|{$count}|{$packaging->id}";
                $sku = $skus->get($skuKey);

                if (! $sku) {
                    $bySize[$displaySize]['no_sku'][] = [
                        'title' => $entry['title'] ?? '',
                        'color' => $color->name,
                        'count' => $count,
                        'packaging' => $packagingName,
                        'barcode' => $barcode,
                        'reason' => 'no SKU for this size/color/count/packaging',
                    ];

                    continue;
                }
            }

            // 6. Guard: check for duplicate barcode on ANY other SKU
            $existingOther = Sku::where('id', '!=', $sku->id)
                ->where(function ($q) use ($barcode) {
                    $q->where('upc', $barcode)->orWhere('ean', $barcode);
                })
                ->exists();

            if ($existingOther) {
                $bySize[$displaySize]['duplicate_code'][] = [
                    'title' => $entry['title'] ?? '',
                    'barcode' => $barcode,
                    'sku_id' => $sku->id,
                    'reason' => 'barcode already assigned to another SKU',
                ];

                continue;
            }

            // 7. Check existing barcode on target SKU
            $existingValue = $field === 'upc' ? $sku->upc : $sku->ean;
            if ($existingValue !== null) {
                $bySize[$displaySize]['already_set'][] = [
                    'title' => $entry['title'] ?? '',
                    'sku_id' => $sku->id,
                    'field' => $field,
                    'existing' => $existingValue,
                    'incoming' => $barcode,
                    'same' => $existingValue === $barcode,
                ];

                continue;
            }

            $bySize[$displaySize]['matched'][] = [
                'title' => $entry['title'] ?? '',
                'sku_id' => $sku->id,
                'barcode' => $barcode,
                'field' => $field,
                'match_type' => $warehouseSku ? 'warehouse_sku' : 'attributes',
            ];
        }

        // ── Render report ────────────────────────────────────────────────────
        $totalMatched = 0;
        ksort($bySize);
        foreach ($bySize as $sizeName => $groups) {
            $matchedCount = count($groups['matched']);
            $totalMatched += $matchedCount;

            $this->line("== {$sizeName} ==");
            $this->line(sprintf(
                '  matched=%d  already-set=%d  parse-fail=%d  no-color=%d  no-sku=%d  invalid-barcode=%d  duplicate-code=%d',
                $matchedCount,
                count($groups['already_set']),
                count($groups['parse_fail']),
                count($groups['no_color']),
                count($groups['no_sku']),
                count($groups['invalid_barcode']),
                count($groups['duplicate_code']),
            ));

            if ($matchedCount > 0) {
                $this->newLine();
                $action = $shouldApply ? 'applied' : 'will apply';
                $this->line("  -- {$action} --");
                foreach ($groups['matched'] as $row) {
                    $this->line("    [{$row['field']}] {$row['barcode']}  ({$row['match_type']})  \"{$row['title']}\"");
                }
            }

            if ($groups['already_set'] !== []) {
                $this->newLine();
                $this->line('  -- already set --');
                foreach ($groups['already_set'] as $row) {
                    $same = $row['same'] ? '(same)' : '⚠ CONFLICT';
                    $this->line("    [{$row['field']}] existing={$row['existing']} incoming={$row['incoming']} {$same}");
                }
            }

            if ($groups['parse_fail'] !== []) {
                $this->newLine();
                $this->line('  -- parse fail --');
                foreach ($groups['parse_fail'] as $row) {
                    $this->line("    \"{$row['title']}\"  barcode={$row['barcode']}");
                }
            }

            if ($groups['no_color'] !== []) {
                $this->newLine();
                $this->line('  -- no color match --');
                foreach ($groups['no_color'] as $row) {
                    $this->line("    texture=\"{$row['texture']}\" color=\"{$row['color']}\"  barcode={$row['barcode']}");
                }
            }

            if ($groups['no_sku'] !== []) {
                $this->newLine();
                $this->line('  -- no SKU match --');
                foreach ($groups['no_sku'] as $row) {
                    $label = isset($row['color'])
                        ? "[{$row['color']}] {$row['count']}CT {$row['packaging']}"
                        : $row['title'];
                    $this->line("    {$label} — {$row['reason']}");
                }
            }

            if ($groups['invalid_barcode'] !== []) {
                $this->newLine();
                $this->line('  -- invalid barcode --');
                foreach ($groups['invalid_barcode'] as $row) {
                    $this->line("    \"{$row['title']}\" barcode={$row['barcode']} — {$row['reason']}");
                }
            }

            if ($groups['duplicate_code'] !== []) {
                $this->newLine();
                $this->line('  -- duplicate barcode --');
                foreach ($groups['duplicate_code'] as $row) {
                    $this->line("    \"{$row['title']}\" barcode={$row['barcode']} — {$row['reason']}");
                }
            }

            $this->newLine();
        }

        // ── Summary ──────────────────────────────────────────────────────────
        $totalParseFail = array_sum(array_map(fn ($g) => count($g['parse_fail']), $bySize));
        $totalNoColor = array_sum(array_map(fn ($g) => count($g['no_color']), $bySize));
        $totalNoSku = array_sum(array_map(fn ($g) => count($g['no_sku']), $bySize));
        $totalAlreadySet = array_sum(array_map(fn ($g) => count($g['already_set']), $bySize));
        $totalInvalid = array_sum(array_map(fn ($g) => count($g['invalid_barcode']), $bySize));
        $totalDuplicate = array_sum(array_map(fn ($g) => count($g['duplicate_code']), $bySize));

        $this->line('== summary ==');
        $this->line(sprintf(
            '  entries=%d  matched=%d  already-set=%d  parse-fail=%d  no-color=%d  no-sku=%d  invalid=%d  duplicate=%d',
            count($entries),
            $totalMatched,
            $totalAlreadySet,
            $totalParseFail,
            $totalNoColor,
            $totalNoSku,
            $totalInvalid,
            $totalDuplicate,
        ));

        if (! $shouldApply) {
            if ($totalMatched > 0) {
                $this->newLine();
                $this->warn("Re-run with --apply to write {$totalMatched} barcode(s) to the database.");
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
                        ->update([$row['field'] => $row['barcode'], 'updated_at' => now()]);
                    $written++;
                }
            }
        });

        $this->info("Applied {$written} barcode(s).");

        return self::SUCCESS;
    }

    // ── Colour resolution ───────────────────────────────────────────────────

    /**
     * Resolve an entry's colour against the Kalisan colour collection.
     *
     * Kalisan colour naming convention:
     *  - Standard (K) texture:  DB colour = just the colour name (e.g. "White")
     *  - All other textures:    DB colour = "{TextureWord} {Colour}" (e.g. "Macaron Lilac")
     *
     * We try the prefixed form first (catches non-Standard), then the bare
     * form (catches Standard and edge cases like Crystal "Clear Transparent").
     *
     * @param  Collection<string, Collection<string, Color>>  $colorsByTextureAndName
     */
    private function resolveColor(
        string $parsedColor,
        string $textureName,
        Collection $colorsByTextureAndName,
    ): ?Color {
        // Strip the " (K)" suffix from texture name to get the prefix word
        $textureWord = str_replace(' (K)', '', $textureName);

        // Try 1: "{TextureWord} {Color}"  (e.g. "Macaron Lilac")
        $prefixedName = "{$textureWord} {$parsedColor}";
        $color = $colorsByTextureAndName->get($textureName)?->get($prefixedName);
        if ($color) {
            return $color;
        }

        // Try 2: Just the colour name (e.g. "White" for Standard)
        $color = $colorsByTextureAndName->get($textureName)?->get($parsedColor);
        if ($color) {
            return $color;
        }

        // Try 3: Alias map (Gray → Grey, etc.)
        $aliased = self::COLOR_NAME_ALIASES[$prefixedName] ?? null;
        if ($aliased) {
            $color = $colorsByTextureAndName->get($textureName)?->get($aliased);
            if ($color) {
                return $color;
            }
        }

        // Try 4: Alias on bare name
        $aliased = self::COLOR_NAME_ALIASES["{$textureWord} {$parsedColor}"]
            ?? self::COLOR_NAME_ALIASES[$parsedColor]
            ?? null;
        if ($aliased) {
            $color = $colorsByTextureAndName->get($textureName)?->get($aliased);
            if ($color) {
                return $color;
            }
        }

        return null;
    }

    /**
     * Map a Color to a compound key of "{texture_name}|{color_name}" for
     * efficient lookup by texture + name.
     */
    private function colorToTextureKey(Color $color): string
    {
        return $color->texture->name;
    }
}
