<?php

namespace Tests\Feature;

use App\Models\BalloonList;
use App\Models\Bin;
use App\Models\Brand;
use App\Models\BrandGs1Prefix;
use App\Models\Business;
use App\Models\Job;
use App\Models\Location;
use App\Models\Membership;
use App\Models\Sku;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\User;
use App\Scopes\BusinessScope;
use App\Support\BusinessContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScanControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Business $business;

    private Bin $bin;

    private Sku $sku;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['email_verified_at' => now()]);
        $this->business = Business::factory()->create();

        Membership::create([
            'user_id' => $this->owner->id,
            'business_id' => $this->business->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        $location = Location::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'name' => 'Default',
            'is_default' => true,
        ]);

        $this->bin = Bin::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'location_id' => $location->id,
            'name' => 'Default',
            'is_default' => true,
        ]);

        BalloonList::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'name' => 'Favorites',
            'is_business_favorites' => true,
            'created_by_user_id' => $this->owner->id,
        ]);

        BusinessContext::set($this->business->id);

        $this->sku = Sku::factory()->create([
            'upc' => '012345678901',
            'name' => 'Test Balloon',
        ]);
    }

    protected function tearDown(): void
    {
        BusinessContext::clear();
        parent::tearDown();
    }

    // ── index ────────────────────────────────────────────────────────────────────

    public function test_index_returns_ok_for_authenticated_owner(): void
    {
        $this->actingAs($this->owner)
            ->get(route('scan.index'))
            ->assertOk();
    }

    public function test_index_requires_authentication(): void
    {
        $this->get(route('scan.index'))
            ->assertRedirect(route('login'));
    }

    // ── lookup ───────────────────────────────────────────────────────────────────

    public function test_lookup_finds_sku_by_upc(): void
    {
        $this->actingAs($this->owner)
            ->postJson(route('scan.lookup'), ['upc' => '012345678901'])
            ->assertOk()
            ->assertJson([
                'found' => true,
                'sku' => [
                    'id' => $this->sku->id,
                    'name' => 'Test Balloon',
                ],
            ]);
    }

    public function test_lookup_returns_not_found_for_unknown_upc(): void
    {
        $this->actingAs($this->owner)
            ->postJson(route('scan.lookup'), ['upc' => '999999999999'])
            ->assertOk()
            ->assertJson(['found' => false]);
    }

    public function test_lookup_resolves_scan_with_scanner_prepended_leading_zero(): void
    {
        // Scanner re-emitted the 12-digit UPC-A as a 13-digit EAN-13 with a
        // leading zero. After GTIN-14 canonicalization both forms collapse
        // to the same value, so this is a plain gtin_exact match.
        $this->actingAs($this->owner)
            ->postJson(route('scan.lookup'), ['upc' => '0012345678901'])
            ->assertOk()
            ->assertJson([
                'found' => true,
                'match_type' => 'gtin_exact',
                'sku' => ['id' => $this->sku->id],
            ]);
    }

    public function test_lookup_returns_candidates_array_and_auto_apply_for_unique_exact_match(): void
    {
        $this->actingAs($this->owner)
            ->postJson(route('scan.lookup'), ['upc' => '012345678901'])
            ->assertOk()
            ->assertJson([
                'found' => true,
                'auto_apply' => true,
                'match_type' => 'gtin_exact',
                'candidates' => [
                    [
                        'match' => 'gtin_exact',
                        'score' => 100,
                        'sku' => ['id' => $this->sku->id],
                    ],
                ],
            ]);
    }

    public function test_lookup_exposes_gtin14_and_is_valid_gtin(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson(route('scan.lookup'), ['upc' => '012345678905']) // valid check digit
            ->assertOk();

        $response->assertJsonPath('gtin14', '00012345678905');
        $response->assertJsonPath('is_valid_gtin', true);
    }

    public function test_lookup_reports_invalid_gtin_when_check_digit_fails(): void
    {
        // Fixture uses 012345678901 — body 01234567890, expected check 5,
        // stored 1. Matches the SKU (exact digit equality) but is_valid_gtin
        // should still report the truth about the scan itself.
        $response = $this->actingAs($this->owner)
            ->postJson(route('scan.lookup'), ['upc' => '012345678901'])
            ->assertOk();

        $response->assertJsonPath('is_valid_gtin', false);
    }

    public function test_lookup_returns_not_found_includes_gtin14_and_auto_apply_false(): void
    {
        $this->actingAs($this->owner)
            ->postJson(route('scan.lookup'), ['upc' => '999999999993'])
            ->assertOk()
            ->assertJson([
                'found' => false,
                'auto_apply' => false,
                'gtin14' => '00999999999993',
                'is_valid_gtin' => true,
            ]);
    }

    public function test_lookup_auto_apply_false_for_low_confidence_match(): void
    {
        // GS1-prefix fallback (score 60) — no UPC stored, just warehouse_sku.
        $brand = Brand::factory()->create();
        BrandGs1Prefix::create(['brand_id' => $brand->id, 'prefix' => '071444']);
        $inferred = Sku::factory()->create([
            'brand_id' => $brand->id,
            'upc' => null,
            'warehouse_sku' => '43734',
            'name' => 'Inferred Match',
        ]);

        $this->actingAs($this->owner)
            ->postJson(route('scan.lookup'), ['upc' => '071444437345'])
            ->assertOk()
            ->assertJson([
                'found' => true,
                'auto_apply' => false,
                'match_type' => 'gs1_warehouse_sku',
                'candidates' => [
                    [
                        'match' => 'gs1_warehouse_sku',
                        'score' => 60,
                        'sku' => ['id' => $inferred->id],
                    ],
                ],
            ]);
    }

    public function test_lookup_auto_apply_false_when_multiple_candidates(): void
    {
        // Both SKUs share the scan's GS1 prefix and contain its tail in
        // their warehouse_sku — the matcher returns two candidates, so we
        // must not auto-apply even though each is a valid GS1 hit.
        $brand = Brand::factory()->create();
        BrandGs1Prefix::create(['brand_id' => $brand->id, 'prefix' => '071444']);
        Sku::factory()->create(['brand_id' => $brand->id, 'upc' => null, 'warehouse_sku' => '43734', 'name' => 'A']);
        Sku::factory()->create(['brand_id' => $brand->id, 'upc' => null, 'mfg_no' => '43734', 'name' => 'B']);

        $response = $this->actingAs($this->owner)
            ->postJson(route('scan.lookup'), ['upc' => '071444437345'])
            ->assertOk();

        $response->assertJsonPath('found', true);
        $response->assertJsonPath('auto_apply', false);
        $this->assertCount(2, $response->json('candidates'));
    }

    public function test_lookup_requires_upc(): void
    {
        $this->actingAs($this->owner)
            ->postJson(route('scan.lookup'), [])
            ->assertUnprocessable();
    }

    // ── check-in ─────────────────────────────────────────────────────────────────

    public function test_check_in_creates_stock_movement_and_updates_level(): void
    {
        $this->actingAs($this->owner)
            ->postJson(route('scan.check-in'), [
                'sku_id' => $this->sku->id,
                'full_bags_change' => 2,
                'open_bags_change' => 0,
            ])
            ->assertOk()
            ->assertJson([
                'recorded' => true,
                'direction' => 'in',
                'full_bags_change' => 2,
            ]);

        $this->assertDatabaseHas('stock_levels', [
            'business_id' => $this->business->id,
            'sku_id' => $this->sku->id,
            'full_bags' => 2,
            'open_bags' => 0,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'business_id' => $this->business->id,
            'sku_id' => $this->sku->id,
            'full_bags_change' => 2,
        ]);
    }

    public function test_check_in_with_open_bags(): void
    {
        $this->actingAs($this->owner)
            ->postJson(route('scan.check-in'), [
                'sku_id' => $this->sku->id,
                'full_bags_change' => 1,
                'open_bags_change' => 1,
            ])
            ->assertOk()
            ->assertJson([
                'recorded' => true,
                'full_bags_change' => 1,
                'open_bags_change' => 1,
            ]);

        $this->assertDatabaseHas('stock_levels', [
            'business_id' => $this->business->id,
            'sku_id' => $this->sku->id,
            'full_bags' => 1,
            'open_bags' => 1,
        ]);
    }

    public function test_check_in_accumulates_on_existing_stock_level(): void
    {
        // Seed existing stock
        StockLevel::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'sku_id' => $this->sku->id,
            'bin_id' => $this->bin->id,
            'full_bags' => 5,
            'open_bags' => 1,
        ]);

        $this->actingAs($this->owner)
            ->postJson(route('scan.check-in'), [
                'sku_id' => $this->sku->id,
                'full_bags_change' => 3,
                'open_bags_change' => 1,
            ])
            ->assertOk();

        $this->assertDatabaseHas('stock_levels', [
            'business_id' => $this->business->id,
            'sku_id' => $this->sku->id,
            'full_bags' => 8,
            'open_bags' => 2,
        ]);
    }

    public function test_check_in_rejects_zero_quantity(): void
    {
        $this->actingAs($this->owner)
            ->postJson(route('scan.check-in'), [
                'sku_id' => $this->sku->id,
                'full_bags_change' => 0,
                'open_bags_change' => 0,
            ])
            ->assertUnprocessable();
    }

    // ── check-out ────────────────────────────────────────────────────────────────

    public function test_check_out_decrements_stock_level(): void
    {
        StockLevel::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'sku_id' => $this->sku->id,
            'bin_id' => $this->bin->id,
            'full_bags' => 5,
            'open_bags' => 1,
        ]);

        $this->actingAs($this->owner)
            ->postJson(route('scan.check-out'), [
                'sku_id' => $this->sku->id,
                'full_bags_change' => 1,
                'open_bags_change' => 0,
            ])
            ->assertOk()
            ->assertJson([
                'recorded' => true,
                'direction' => 'out',
            ]);

        $this->assertDatabaseHas('stock_levels', [
            'business_id' => $this->business->id,
            'sku_id' => $this->sku->id,
            'full_bags' => 4,
            'open_bags' => 1,
        ]);
    }

    // ── undo ─────────────────────────────────────────────────────────────────────

    public function test_undo_reverses_check_in(): void
    {
        StockLevel::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'sku_id' => $this->sku->id,
            'bin_id' => $this->bin->id,
            'full_bags' => 3,
            'open_bags' => 0,
        ]);

        $movement = StockMovement::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'sku_id' => $this->sku->id,
            'bin_id' => $this->bin->id,
            'user_id' => $this->owner->id,
            'direction' => 'in',
            'full_bags_change' => 2,
            'open_bags_change' => 0,
        ]);

        $this->actingAs($this->owner)
            ->postJson(route('scan.undo', ['stockMovement' => $movement->id]))
            ->assertOk()
            ->assertJson(['undone' => true]);

        // Stock level should be back to original
        $this->assertDatabaseHas('stock_levels', [
            'business_id' => $this->business->id,
            'sku_id' => $this->sku->id,
            'full_bags' => 1,
            'open_bags' => 0,
        ]);
    }

    public function test_undo_reverses_check_out(): void
    {
        StockLevel::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'sku_id' => $this->sku->id,
            'bin_id' => $this->bin->id,
            'full_bags' => 1,
            'open_bags' => 0,
        ]);

        $movement = StockMovement::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'sku_id' => $this->sku->id,
            'bin_id' => $this->bin->id,
            'user_id' => $this->owner->id,
            'direction' => 'out',
            'full_bags_change' => 1,
            'open_bags_change' => 0,
        ]);

        $this->actingAs($this->owner)
            ->postJson(route('scan.undo', ['stockMovement' => $movement->id]))
            ->assertOk();

        $this->assertDatabaseHas('stock_levels', [
            'business_id' => $this->business->id,
            'sku_id' => $this->sku->id,
            'full_bags' => 2,
            'open_bags' => 0,
        ]);
    }

    public function test_undo_of_check_in_is_rejected_when_stock_already_removed(): void
    {
        // Check in 2 (level 0 -> 2), then those bags are checked out elsewhere
        // (level 2 -> 0). Undoing the original check-in would decrement below
        // zero, so it must be rejected rather than corrupt the level.
        StockLevel::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'sku_id' => $this->sku->id,
            'bin_id' => $this->bin->id,
            'full_bags' => 0,
            'open_bags' => 0,
        ]);

        $movement = StockMovement::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'sku_id' => $this->sku->id,
            'bin_id' => $this->bin->id,
            'user_id' => $this->owner->id,
            'direction' => 'in',
            'full_bags_change' => 2,
            'open_bags_change' => 0,
        ]);

        $this->actingAs($this->owner)
            ->postJson(route('scan.undo', ['stockMovement' => $movement->id]))
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('stock');

        // Level stays at zero and no reverse movement was written.
        $this->assertDatabaseHas('stock_levels', [
            'business_id' => $this->business->id,
            'sku_id' => $this->sku->id,
            'full_bags' => 0,
            'open_bags' => 0,
        ]);
        $this->assertDatabaseMissing('stock_movements', [
            'sku_id' => $this->sku->id,
            'direction' => 'out',
        ]);
    }

    public function test_undo_rejects_movement_from_other_business(): void
    {
        $otherBusiness = Business::factory()->create();

        $otherLocation = Location::withoutGlobalScope(BusinessScope::class)
            ->create(['business_id' => $otherBusiness->id, 'name' => 'Default', 'is_default' => true]);

        $otherBin = Bin::withoutGlobalScope(BusinessScope::class)
            ->create(['business_id' => $otherBusiness->id, 'location_id' => $otherLocation->id, 'name' => 'Default', 'is_default' => true]);

        $movement = StockMovement::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $otherBusiness->id,
            'sku_id' => $this->sku->id,
            'bin_id' => $otherBin->id,
            'user_id' => $this->owner->id,
            'direction' => 'in',
            'full_bags_change' => 1,
            'open_bags_change' => 0,
        ]);

        $this->actingAs($this->owner)
            ->postJson(route('scan.undo', ['stockMovement' => $movement->id]))
            ->assertNotFound();
    }

    // ── Auto-creates default bin ─────────────────────────────────────────────────

    public function test_check_in_creates_default_bin_when_none_exists(): void
    {
        // Create a fresh business with no bins or locations.
        $freshBusiness = Business::factory()->create();
        Membership::create([
            'user_id' => $this->owner->id,
            'business_id' => $freshBusiness->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);
        BusinessContext::set($freshBusiness->id);

        $this->actingAs($this->owner)
            ->withSession(['current_business_id' => $freshBusiness->id])
            ->postJson(route('scan.check-in'), [
                'sku_id' => $this->sku->id,
                'full_bags_change' => 1,
                'open_bags_change' => 0,
            ])
            ->assertOk();

        $this->assertDatabaseHas('locations', [
            'business_id' => $freshBusiness->id,
            'name' => 'Default',
            'is_default' => true,
        ]);

        $this->assertDatabaseHas('bins', [
            'business_id' => $freshBusiness->id,
            'name' => 'Default',
            'is_default' => true,
        ]);
    }

    // ── Tenant isolation on sku_id ───────────────────────────────────────────────

    public function test_check_in_rejects_sku_owned_by_another_business(): void
    {
        $otherBusiness = Business::factory()->create();

        $foreignSku = Sku::factory()->create([
            'upc' => '999000111222',
            'name' => 'Foreign SKU',
            'owned_by_business_id' => $otherBusiness->id,
        ]);

        $this->actingAs($this->owner)
            ->postJson(route('scan.check-in'), [
                'sku_id' => $foreignSku->id,
                'full_bags_change' => 1,
                'open_bags_change' => 0,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sku_id']);

        $this->assertDatabaseMissing('stock_movements', [
            'sku_id' => $foreignSku->id,
            'business_id' => $this->business->id,
        ]);
    }

    public function test_check_in_accepts_shared_catalog_sku(): void
    {
        $sharedSku = Sku::factory()->create([
            'upc' => '888777666555',
            'name' => 'Shared SKU',
            'owned_by_business_id' => null,
        ]);

        $this->actingAs($this->owner)
            ->postJson(route('scan.check-in'), [
                'sku_id' => $sharedSku->id,
                'full_bags_change' => 1,
                'open_bags_change' => 0,
            ])
            ->assertOk();

        $this->assertDatabaseHas('stock_movements', [
            'sku_id' => $sharedSku->id,
            'business_id' => $this->business->id,
        ]);
    }

    public function test_check_in_accepts_sku_owned_by_current_business(): void
    {
        $ownedSku = Sku::factory()->create([
            'upc' => '777666555444',
            'name' => 'Owned SKU',
            'owned_by_business_id' => $this->business->id,
        ]);

        $this->actingAs($this->owner)
            ->postJson(route('scan.check-in'), [
                'sku_id' => $ownedSku->id,
                'full_bags_change' => 1,
                'open_bags_change' => 0,
            ])
            ->assertOk();
    }

    public function test_check_in_rejects_soft_deleted_sku(): void
    {
        $this->sku->delete();

        $this->actingAs($this->owner)
            ->postJson(route('scan.check-in'), [
                'sku_id' => $this->sku->id,
                'full_bags_change' => 1,
                'open_bags_change' => 0,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sku_id']);

        $this->assertDatabaseMissing('stock_movements', [
            'sku_id' => $this->sku->id,
        ]);
    }

    public function test_lookup_does_not_return_sku_owned_by_another_business(): void
    {
        $otherBusiness = Business::factory()->create();
        Sku::factory()->create([
            'upc' => '555444333222',
            'name' => 'Foreign SKU',
            'owned_by_business_id' => $otherBusiness->id,
        ]);

        $this->actingAs($this->owner)
            ->postJson(route('scan.lookup'), ['upc' => '555444333222'])
            ->assertOk()
            ->assertJson(['found' => false]);
    }

    // ── upc_scanned audit trail ──────────────────────────────────────────────────

    public function test_check_in_stores_scanned_upc_on_movement(): void
    {
        $this->actingAs($this->owner)
            ->postJson(route('scan.check-in'), [
                'sku_id' => $this->sku->id,
                'upc' => '012345678901',
                'full_bags_change' => 1,
                'open_bags_change' => 0,
            ])
            ->assertOk();

        $this->assertDatabaseHas('stock_movements', [
            'sku_id' => $this->sku->id,
            'upc_scanned' => '012345678901',
        ]);
    }

    public function test_check_in_normalizes_empty_upc_to_null(): void
    {
        $this->actingAs($this->owner)
            ->postJson(route('scan.check-in'), [
                'sku_id' => $this->sku->id,
                'upc' => '   ',
                'full_bags_change' => 1,
                'open_bags_change' => 0,
            ])
            ->assertOk();

        $this->assertDatabaseHas('stock_movements', [
            'sku_id' => $this->sku->id,
            'upc_scanned' => null,
        ]);
    }

    // ── Negative-stock guard ─────────────────────────────────────────────────────

    public function test_check_out_rejects_removal_exceeding_full_bags(): void
    {
        StockLevel::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'sku_id' => $this->sku->id,
            'bin_id' => $this->bin->id,
            'full_bags' => 2,
            'open_bags' => 0,
        ]);

        $this->actingAs($this->owner)
            ->postJson(route('scan.check-out'), [
                'sku_id' => $this->sku->id,
                'full_bags_change' => 5,
                'open_bags_change' => 0,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['full_bags_change']);

        // Stock level untouched.
        $this->assertDatabaseHas('stock_levels', [
            'business_id' => $this->business->id,
            'sku_id' => $this->sku->id,
            'full_bags' => 2,
        ]);

        // No movement written.
        $this->assertDatabaseMissing('stock_movements', [
            'sku_id' => $this->sku->id,
            'direction' => 'out',
        ]);
    }

    public function test_check_out_rejects_removal_when_no_stock_exists(): void
    {
        $this->actingAs($this->owner)
            ->postJson(route('scan.check-out'), [
                'sku_id' => $this->sku->id,
                'full_bags_change' => 1,
                'open_bags_change' => 0,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['full_bags_change']);
    }

    public function test_check_out_rejects_removal_exceeding_open_bags(): void
    {
        StockLevel::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'sku_id' => $this->sku->id,
            'bin_id' => $this->bin->id,
            'full_bags' => 10,
            'open_bags' => 0,
        ]);

        $this->actingAs($this->owner)
            ->postJson(route('scan.check-out'), [
                'sku_id' => $this->sku->id,
                'full_bags_change' => 0,
                'open_bags_change' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['full_bags_change']);
    }

    // ── Job tenant isolation ─────────────────────────────────────────────────────

    public function test_check_out_rejects_job_id_from_another_business(): void
    {
        $otherBusiness = Business::factory()->create();

        $foreignJob = Job::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $otherBusiness->id,
            'name' => 'Foreign Job',
            'status' => 'draft',
            'created_by_user_id' => $this->owner->id,
        ]);

        StockLevel::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'sku_id' => $this->sku->id,
            'bin_id' => $this->bin->id,
            'full_bags' => 5,
            'open_bags' => 0,
        ]);

        $this->actingAs($this->owner)
            ->postJson(route('scan.check-out'), [
                'sku_id' => $this->sku->id,
                'full_bags_change' => 1,
                'open_bags_change' => 0,
                'job_id' => $foreignJob->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['job_id']);
    }

    // ── Movement-ID race correctness ─────────────────────────────────────────────

    public function test_check_in_returns_id_of_the_movement_it_just_created(): void
    {
        // Pre-seed an older movement for the same SKU/business so that
        // `latest('created_at')` (the old buggy pattern) would have returned
        // a different id under high concurrency.
        $oldMovement = StockMovement::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'sku_id' => $this->sku->id,
            'bin_id' => $this->bin->id,
            'user_id' => $this->owner->id,
            'direction' => 'in',
            'full_bags_change' => 1,
            'open_bags_change' => 0,
            'created_at' => now()->subSecond(),
        ]);

        $response = $this->actingAs($this->owner)
            ->postJson(route('scan.check-in'), [
                'sku_id' => $this->sku->id,
                'full_bags_change' => 2,
                'open_bags_change' => 0,
            ])
            ->assertOk();

        $returnedId = $response->json('movement_id');

        $this->assertNotEquals($oldMovement->id, $returnedId);

        // The returned id corresponds to a movement with the right delta.
        $this->assertDatabaseHas('stock_movements', [
            'id' => $returnedId,
            'sku_id' => $this->sku->id,
            'full_bags_change' => 2,
        ]);
    }

    // ── bin inference & selection (Phase 2) ───────────────────────────────────────

    private function makeBin(string $name, ?int $number = null): Bin
    {
        return Bin::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'location_id' => $this->bin->location_id,
            'name' => $name,
            'number' => $number,
        ]);
    }

    private function stock(Bin $bin, int $full, int $open, ?string $movedAt = null): void
    {
        StockLevel::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'sku_id' => $this->sku->id,
            'bin_id' => $bin->id,
            'full_bags' => $full,
            'open_bags' => $open,
            'last_movement_at' => $movedAt,
        ]);
    }

    public function test_index_provides_bins_for_the_working_bin_selector(): void
    {
        $shelf = $this->makeBin('Top Shelf', 3);

        $this->actingAs($this->owner)
            ->get(route('scan.index'))
            ->assertInertia(fn ($page) => $page
                ->where('defaultBinId', $this->bin->id)
                ->has('bins', 2)
                // Default bin is listed first (is_default desc).
                ->where('bins.0.id', $this->bin->id)
                ->where('bins.1.id', $shelf->id)
                ->where('bins.1.name', 'Top Shelf')
            );
    }

    public function test_lookup_reports_the_bin_a_sku_lives_in(): void
    {
        $shelf = $this->makeBin('Top Shelf');
        $this->stock($shelf, full: 4, open: 0, movedAt: now()->toDateTimeString());

        $this->actingAs($this->owner)
            ->postJson(route('scan.lookup'), ['upc' => '012345678901'])
            ->assertOk()
            ->assertJsonPath('sku.suggested_bin_id', $shelf->id)
            ->assertJsonPath('sku.bins.0.bin_id', $shelf->id)
            ->assertJsonPath('sku.bins.0.full_bags', 4);
    }

    public function test_lookup_suggests_the_most_recently_moved_bin_when_stock_spans_bins(): void
    {
        $older = $this->makeBin('Older');
        $newer = $this->makeBin('Newer');
        $this->stock($older, full: 1, open: 0, movedAt: now()->subDays(5)->toDateTimeString());
        $this->stock($newer, full: 1, open: 0, movedAt: now()->toDateTimeString());

        $this->actingAs($this->owner)
            ->postJson(route('scan.lookup'), ['upc' => '012345678901'])
            ->assertOk()
            ->assertJsonPath('sku.suggested_bin_id', $newer->id)
            ->assertJsonCount(2, 'sku.bins');
    }

    public function test_lookup_suggests_no_bin_when_sku_has_no_stock(): void
    {
        $this->actingAs($this->owner)
            ->postJson(route('scan.lookup'), ['upc' => '012345678901'])
            ->assertOk()
            ->assertJsonPath('sku.suggested_bin_id', null)
            ->assertJsonCount(0, 'sku.bins');
    }

    public function test_check_in_records_to_the_selected_bin(): void
    {
        $shelf = $this->makeBin('Top Shelf');

        $this->actingAs($this->owner)
            ->postJson(route('scan.check-in'), [
                'sku_id' => $this->sku->id,
                'bin_id' => $shelf->id,
                'full_bags_change' => 3,
                'open_bags_change' => 0,
            ])
            ->assertOk()
            ->assertJsonPath('bin.id', $shelf->id);

        $this->assertDatabaseHas('stock_levels', [
            'sku_id' => $this->sku->id,
            'bin_id' => $shelf->id,
            'full_bags' => 3,
        ]);
        // Nothing should have landed in the Default bin.
        $this->assertDatabaseMissing('stock_levels', [
            'sku_id' => $this->sku->id,
            'bin_id' => $this->bin->id,
        ]);
    }

    public function test_check_in_rejects_a_bin_from_another_business(): void
    {
        $otherBusiness = Business::factory()->create();
        $otherLocation = Location::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $otherBusiness->id, 'name' => 'Default', 'is_default' => true,
        ]);
        $otherBin = Bin::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $otherBusiness->id, 'location_id' => $otherLocation->id, 'name' => 'Foreign',
        ]);

        $this->actingAs($this->owner)
            ->postJson(route('scan.check-in'), [
                'sku_id' => $this->sku->id,
                'bin_id' => $otherBin->id,
                'full_bags_change' => 1,
                'open_bags_change' => 0,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('bin_id');
    }
}
