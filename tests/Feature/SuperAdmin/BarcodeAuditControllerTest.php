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
}
