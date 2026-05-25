<?php

namespace Tests\Feature\Console;

use App\Models\Brand;
use App\Models\Sku;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditBarcodesTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_reports_categories_without_modifying_data(): void
    {
        $brand = Brand::factory()->create(['name' => 'Acme']);
        $valid = Sku::factory()->create(['brand_id' => $brand->id, 'upc' => '012345678905']);
        $missing = Sku::factory()->create(['brand_id' => $brand->id, 'upc' => '01234567890']);
        $invalidCheck = Sku::factory()->create(['brand_id' => $brand->id, 'upc' => '012345678900']);

        $this->artisan('catalog:audit-barcodes')
            ->assertExitCode(0);

        // Dry run is the default — nothing should change.
        $this->assertSame('012345678905', $valid->fresh()->upc);
        $this->assertSame('01234567890', $missing->fresh()->upc);
        $this->assertSame('012345678900', $invalidCheck->fresh()->upc);
    }

    public function test_fix_missing_check_digit_only_patches_eligible_rows(): void
    {
        $brand = Brand::factory()->create();

        $missingA = Sku::factory()->create(['brand_id' => $brand->id, 'upc' => '01234567890']);
        $missingB = Sku::factory()->create(['brand_id' => $brand->id, 'upc' => '03062557539']);
        $valid = Sku::factory()->create(['brand_id' => $brand->id, 'upc' => '036000241457']);
        $invalidCheck = Sku::factory()->create(['brand_id' => $brand->id, 'upc' => '012345678900']);

        $this->artisan('catalog:audit-barcodes', ['--fix-missing-check-digit' => true])
            ->assertExitCode(0);

        // Patched: appended computed check digit.
        $this->assertSame('012345678905', $missingA->fresh()->upc);
        $this->assertSame('030625575393', $missingB->fresh()->upc);

        // Untouched.
        $this->assertSame('036000241457', $valid->fresh()->upc);
        $this->assertSame('012345678900', $invalidCheck->fresh()->upc);
    }

    public function test_fix_is_idempotent(): void
    {
        $brand = Brand::factory()->create();
        $sku = Sku::factory()->create(['brand_id' => $brand->id, 'upc' => '01234567890']);

        $this->artisan('catalog:audit-barcodes', ['--fix-missing-check-digit' => true])
            ->assertExitCode(0);

        $this->assertSame('012345678905', $sku->fresh()->upc);

        // Second run: no missing-check rows remain. Re-running is safe.
        $this->artisan('catalog:audit-barcodes', ['--fix-missing-check-digit' => true])
            ->assertExitCode(0);

        $this->assertSame('012345678905', $sku->fresh()->upc);
    }

    public function test_audit_handles_stored_ean(): void
    {
        // The auto-fix only fires on UNAMBIGUOUSLY-short values — lengths
        // that are one digit short of a valid GTIN length AND not themselves
        // valid. 11 fits (next valid is 12), 12 does not (12 is itself a
        // valid GTIN-12 length, so we can't tell complete-UPC-A from short-EAN).
        $brand = Brand::factory()->create();
        $sku = Sku::factory()->create([
            'brand_id' => $brand->id,
            'upc' => null,
            'ean' => '11111000000',
        ]);

        $this->artisan('catalog:audit-barcodes', ['--fix-missing-check-digit' => true])
            ->assertExitCode(0);

        $patched = $sku->fresh()->ean;
        $this->assertSame(12, strlen($patched));
        $this->assertSame('11111000000', substr($patched, 0, 11));
    }

    public function test_columns_option_restricts_scope(): void
    {
        $brand = Brand::factory()->create();
        $upcOnly = Sku::factory()->create(['brand_id' => $brand->id, 'upc' => '01234567890']);
        $eanOnly = Sku::factory()->create([
            'brand_id' => $brand->id,
            'upc' => null,
            'ean' => '11111000000',
        ]);

        $this->artisan('catalog:audit-barcodes', [
            '--fix-missing-check-digit' => true,
            '--columns' => 'upc',
        ])->assertExitCode(0);

        // upc fixed, ean untouched.
        $this->assertSame('012345678905', $upcOnly->fresh()->upc);
        $this->assertSame('11111000000', $eanOnly->fresh()->ean);
    }

    public function test_rejects_unknown_column(): void
    {
        $this->artisan('catalog:audit-barcodes', ['--columns' => 'mfg_no'])
            ->assertExitCode(1);
    }

    public function test_unrecognized_length_is_reported_but_not_patched(): void
    {
        $brand = Brand::factory()->create();
        $weird = Sku::factory()->create(['brand_id' => $brand->id, 'upc' => '12345']);

        $this->artisan('catalog:audit-barcodes', ['--fix-missing-check-digit' => true])
            ->assertExitCode(0);

        $this->assertSame('12345', $weird->fresh()->upc);
    }
}
