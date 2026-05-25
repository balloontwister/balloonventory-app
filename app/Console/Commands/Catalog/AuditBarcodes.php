<?php

namespace App\Console\Commands\Catalog;

use App\Models\Sku;
use App\Support\Gtin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditBarcodes extends Command
{
    protected $signature = 'catalog:audit-barcodes
                            {--fix-missing-check-digit : Patch rows whose stored value is one digit short of a valid GTIN length by appending the computed check digit}
                            {--columns=upc,ean : Comma-separated columns to audit (any subset of upc,ean)}';

    protected $description = 'Audit skus.upc / skus.ean for invalid GTINs. Optionally back-fill rows missing their trailing check digit.';

    /** @var array<int, int> Valid GTIN lengths (8/12/13/14). */
    private const VALID_LENGTHS = [8, 12, 13, 14];

    public function handle(): int
    {
        $columns = $this->resolveColumns();
        if ($columns === null) {
            return self::FAILURE;
        }

        $shouldFix = (bool) $this->option('fix-missing-check-digit');

        $totals = [
            'scanned' => 0,
            'valid' => 0,
            'missing_check_digit' => 0,
            'invalid_check_digit' => 0,
            'unrecognized_length' => 0,
            'fixed' => 0,
        ];

        foreach ($columns as $column) {
            $this->line("== auditing skus.{$column} ==");

            $report = $this->auditColumn($column, $shouldFix);

            foreach ($report['totals'] as $key => $value) {
                $totals[$key] += $value;
            }

            $this->renderColumnReport($column, $report);
        }

        $this->newLine();
        $this->line('== summary ==');
        $this->line(sprintf(
            '  scanned: %d / valid: %d / missing-check: %d / invalid-check: %d / bad-length: %d / fixed: %d',
            $totals['scanned'],
            $totals['valid'],
            $totals['missing_check_digit'],
            $totals['invalid_check_digit'],
            $totals['unrecognized_length'],
            $totals['fixed'],
        ));

        if (! $shouldFix && $totals['missing_check_digit'] > 0) {
            $this->newLine();
            $this->warn("Re-run with --fix-missing-check-digit to patch {$totals['missing_check_digit']} row(s) missing their check digit.");
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>|null
     */
    private function resolveColumns(): ?array
    {
        $raw = (string) $this->option('columns');
        $columns = array_values(array_filter(array_map('trim', explode(',', $raw))));

        foreach ($columns as $column) {
            if (! in_array($column, ['upc', 'ean'], true)) {
                $this->error("Unknown column: {$column}. Allowed: upc, ean.");

                return null;
            }
        }

        return $columns === [] ? ['upc', 'ean'] : $columns;
    }

    /**
     * @return array{
     *     totals: array{scanned:int, valid:int, missing_check_digit:int, invalid_check_digit:int, unrecognized_length:int, fixed:int},
     *     missing: array<int, array{id:string, brand:string, name:string, stored:string, fix:string}>,
     *     invalid: array<int, array{id:string, brand:string, name:string, stored:string, expected:string, got:string}>,
     *     bad_length: array<int, array{id:string, brand:string, name:string, stored:string, length:int}>
     * }
     */
    private function auditColumn(string $column, bool $shouldFix): array
    {
        $totals = [
            'scanned' => 0,
            'valid' => 0,
            'missing_check_digit' => 0,
            'invalid_check_digit' => 0,
            'unrecognized_length' => 0,
            'fixed' => 0,
        ];

        $missing = [];
        $invalid = [];
        $badLength = [];

        Sku::query()
            ->whereNotNull($column)
            ->with('brand:id,name')
            ->select(['id', 'brand_id', 'name', $column])
            ->chunkById(500, function ($skus) use ($column, $shouldFix, &$totals, &$missing, &$invalid, &$badLength) {
                foreach ($skus as $sku) {
                    $totals['scanned']++;

                    $stored = (string) $sku->{$column};
                    $digits = Gtin::digitsOnly($stored);
                    $length = strlen($digits);

                    if (in_array($length, self::VALID_LENGTHS, true)) {
                        if (Gtin::isValidCheckDigit($digits)) {
                            $totals['valid']++;
                        } else {
                            $totals['invalid_check_digit']++;
                            $body = substr($digits, 0, -1);
                            $invalid[] = [
                                'id' => $sku->id,
                                'brand' => $sku->brand?->name ?? '?',
                                'name' => $sku->name,
                                'stored' => $stored,
                                'expected' => (string) Gtin::checkDigit($body),
                                'got' => substr($digits, -1),
                            ];
                        }

                        continue;
                    }

                    if (in_array($length + 1, self::VALID_LENGTHS, true) && $length > 0) {
                        $totals['missing_check_digit']++;
                        $check = Gtin::checkDigit($digits);
                        $fixed = $digits.$check;
                        $missing[] = [
                            'id' => $sku->id,
                            'brand' => $sku->brand?->name ?? '?',
                            'name' => $sku->name,
                            'stored' => $stored,
                            'fix' => $fixed,
                        ];

                        if ($shouldFix) {
                            DB::table('skus')->where('id', $sku->id)->update([$column => $fixed, 'updated_at' => now()]);
                            $totals['fixed']++;
                        }

                        continue;
                    }

                    $totals['unrecognized_length']++;
                    $badLength[] = [
                        'id' => $sku->id,
                        'brand' => $sku->brand?->name ?? '?',
                        'name' => $sku->name,
                        'stored' => $stored,
                        'length' => $length,
                    ];
                }
            });

        return [
            'totals' => $totals,
            'missing' => $missing,
            'invalid' => $invalid,
            'bad_length' => $badLength,
        ];
    }

    /**
     * @param  array{
     *     totals: array{scanned:int, valid:int, missing_check_digit:int, invalid_check_digit:int, unrecognized_length:int, fixed:int},
     *     missing: array<int, array{id:string, brand:string, name:string, stored:string, fix:string}>,
     *     invalid: array<int, array{id:string, brand:string, name:string, stored:string, expected:string, got:string}>,
     *     bad_length: array<int, array{id:string, brand:string, name:string, stored:string, length:int}>
     * }  $report
     */
    private function renderColumnReport(string $column, array $report): void
    {
        $t = $report['totals'];
        $this->line(sprintf(
            '  totals: scanned=%d valid=%d missing-check=%d invalid-check=%d bad-length=%d fixed=%d',
            $t['scanned'],
            $t['valid'],
            $t['missing_check_digit'],
            $t['invalid_check_digit'],
            $t['unrecognized_length'],
            $t['fixed'],
        ));

        if ($report['missing'] !== []) {
            $this->newLine();
            $action = $t['fixed'] > 0 ? 'fixed' : 'missing check digit (auto-fixable)';
            $this->line("  -- {$action} --");
            foreach ($report['missing'] as $row) {
                $this->line("    [{$row['brand']}] \"{$row['name']}\" {$column}={$row['stored']} -> {$row['fix']}");
            }
        }

        if ($report['invalid'] !== []) {
            $this->newLine();
            $this->line('  -- invalid check digit (manual review required) --');
            foreach ($report['invalid'] as $row) {
                $this->line("    [{$row['brand']}] \"{$row['name']}\" {$column}={$row['stored']} (last={$row['got']}, expected={$row['expected']})");
            }
        }

        if ($report['bad_length'] !== []) {
            $this->newLine();
            $this->line('  -- unrecognized length (manual review required) --');
            foreach ($report['bad_length'] as $row) {
                $this->line("    [{$row['brand']}] \"{$row['name']}\" {$column}={$row['stored']} (length={$row['length']})");
            }
        }
    }
}
