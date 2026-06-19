<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\BarcodeLinkAudit;
use App\Models\Business;
use App\Models\Sku;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BarcodeAuditControllerTest extends TestCase
{
    use RefreshDatabase;

    private function seedAudit(array $overrides = []): BarcodeLinkAudit
    {
        $business = Business::factory()->create();
        $user = User::factory()->create();
        $sku = Sku::factory()->create();

        return BarcodeLinkAudit::create(array_merge([
            'business_id' => $business->id,
            'user_id' => $user->id,
            'sku_id' => $sku->id,
            'sku_name' => 'Linked Balloon',
            'barcode' => '8693296864283',
            'field' => 'ean',
        ], $overrides));
    }

    public function test_super_admin_can_view_barcode_audit_log(): void
    {
        $audit = $this->seedAudit();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('super-admin.barcode-audits.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('SuperAdmin/BarcodeAudits/Index')
                ->has('audits.data', 1)
                ->where('audits.data.0.id', $audit->id)
            );
    }

    public function test_regular_user_cannot_view_barcode_audit_log(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('super-admin.barcode-audits.index'))
            ->assertForbidden();
    }

    public function test_audit_log_can_be_searched_by_barcode(): void
    {
        $this->seedAudit(['barcode' => '8693296864283', 'sku_name' => 'Macaron Blue']);
        $this->seedAudit(['barcode' => '0123456789012', 'sku_name' => 'Something Else']);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('super-admin.barcode-audits.index', ['search' => 'Macaron']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('audits.data', 1)
                ->where('audits.data.0.sku_name', 'Macaron Blue')
            );
    }

    // ── revert ──────────────────────────────────────────────────────────────────

    public function test_revert_clears_the_barcode_and_stamps_the_audit(): void
    {
        $sku = Sku::factory()->create(['ean' => '8693296864283', 'upc' => null]);
        $audit = BarcodeLinkAudit::create([
            'sku_id' => $sku->id,
            'sku_name' => $sku->name,
            'barcode' => '8693296864283',
            'field' => 'ean',
        ]);

        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->post(route('super-admin.barcode-audits.revert', $audit->id))
            ->assertRedirect();

        $this->assertDatabaseHas('skus', ['id' => $sku->id, 'ean' => null]);
        $audit->refresh();
        $this->assertNotNull($audit->reverted_at);
        $this->assertSame($admin->id, $audit->reverted_by_user_id);
    }

    public function test_revert_does_not_clobber_a_newer_code(): void
    {
        // The SKU now carries a DIFFERENT code than the one this audit recorded.
        $sku = Sku::factory()->create(['ean' => '0012345678905']);
        $audit = BarcodeLinkAudit::create([
            'sku_id' => $sku->id,
            'sku_name' => $sku->name,
            'barcode' => '8693296864283',
            'field' => 'ean',
        ]);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->post(route('super-admin.barcode-audits.revert', $audit->id))
            ->assertRedirect();

        // Newer code untouched; audit still stamped reverted.
        $this->assertDatabaseHas('skus', ['id' => $sku->id, 'ean' => '0012345678905']);
        $this->assertNotNull($audit->fresh()->reverted_at);
    }

    public function test_revert_is_a_noop_when_already_reverted(): void
    {
        $sku = Sku::factory()->create(['ean' => '8693296864283']);
        $audit = BarcodeLinkAudit::create([
            'sku_id' => $sku->id,
            'sku_name' => $sku->name,
            'barcode' => '8693296864283',
            'field' => 'ean',
            'reverted_at' => now()->subDay(),
        ]);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->post(route('super-admin.barcode-audits.revert', $audit->id))
            ->assertRedirect();

        // Already-reverted: the SKU's current code is left alone.
        $this->assertDatabaseHas('skus', ['id' => $sku->id, 'ean' => '8693296864283']);
    }

    public function test_regular_user_cannot_revert(): void
    {
        $audit = $this->seedAudit();

        $this->actingAs(User::factory()->create())
            ->post(route('super-admin.barcode-audits.revert', $audit->id))
            ->assertForbidden();
    }
}
