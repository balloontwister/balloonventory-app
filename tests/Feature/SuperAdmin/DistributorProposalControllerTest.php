<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\BalloonSize;
use App\Models\BarcodeLinkAudit;
use App\Models\Brand;
use App\Models\Color;
use App\Models\Distributor;
use App\Models\DistributorCatalogProposal;
use App\Models\Sku;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DistributorProposalControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        // The Proposals Vue page is built separately; this suite validates the
        // controller/service contract, not the view.
        config(['inertia.testing.ensure_pages_exist' => false]);
        $this->seed(PermissionSeeder::class);

        $this->admin = User::factory()->create([
            'email_verified_at' => now(),
            'admin_level' => 'super_admin',
        ]);
    }

    public function test_index_lists_proposals_with_hydrated_reference_names(): void
    {
        $brand = Brand::factory()->create();
        $distributor = Distributor::factory()->shopify()->create();

        DistributorCatalogProposal::factory()->create([
            'proposed_brand_id' => $brand->id,
            'evidence' => [[
                'distributor_id' => $distributor->id,
                'title' => 'Example product',
                'url' => 'https://example.com/p/1',
            ]],
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.distributors.proposals.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('SuperAdmin/Distributors/Proposals')
            ->where('proposals.data.0.brand_name', $brand->name)
            ->where('proposals.data.0.evidence.0.distributor_name', $distributor->name)
            ->where('proposals.data.0.distributor_count', 1));
    }

    public function test_approve_with_manual_mapping_creates_a_sku(): void
    {
        $balloonSize = BalloonSize::factory()->create();
        $color = Color::factory()->create(['brand_id' => $balloonSize->brand_id]);

        $proposal = DistributorCatalogProposal::factory()->create([
            'proposed_brand_id' => $balloonSize->brand_id,
            'proposed_balloon_size_id' => $balloonSize->id,
            'proposed_color_id' => $color->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.distributors.proposals.approve', $proposal->id));

        $response->assertSessionHas('success');

        $proposal->refresh();
        $this->assertSame(DistributorCatalogProposal::STATUS_APPROVED, $proposal->status);
        $this->assertNotNull($proposal->resulting_sku_id);
        $this->assertSame($this->admin->id, $proposal->reviewed_by);
        $this->assertDatabaseHas('skus', ['id' => $proposal->resulting_sku_id]);
    }

    public function test_approve_uses_structured_attributes_to_create_a_sku(): void
    {
        $brand = Brand::factory()->create(['name' => 'Kalisan']);
        $size = BalloonSize::factory()->create(['brand_id' => $brand->id, 'name' => '260K']);
        $color = Color::factory()->create(['brand_id' => $brand->id, 'name' => 'Clear Transparent']);
        $distributor = Distributor::factory()->bigcommerce()->create([
            'config' => ['attribute_aliases' => ['color' => ['Standard Clear' => 'Clear Transparent']]],
        ]);

        // A proposal with a barren title (so the title resolver can't help) but a
        // rich structured attribute table from the distributor's page.
        $proposal = DistributorCatalogProposal::factory()->create([
            'proposed_name' => 'Mystery 12345',
            'evidence' => [[
                'distributor_id' => $distributor->id,
                'title' => 'Mystery 12345',
                'attributes' => [
                    'Brand' => ['Kalisan'],
                    'Size' => ['260'],
                    'Color' => ['Standard Clear'],
                    'Quantity' => ['100 ct'],
                ],
            ]],
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.distributors.proposals.approve', $proposal->id))
            ->assertSessionHas('success');

        $proposal->refresh();
        $this->assertNotNull($proposal->resulting_sku_id);
        $this->assertDatabaseHas('skus', [
            'id' => $proposal->resulting_sku_id,
            'brand_id' => $brand->id,
            'balloon_size_id' => $size->id,
            'color_id' => $color->id,
        ]);
    }

    public function test_approve_links_new_sku_to_identical_pack_sizes_but_not_printed(): void
    {
        $brand = Brand::factory()->create(['name' => 'Kalisan']);
        $size = BalloonSize::factory()->create(['brand_id' => $brand->id, 'name' => '260K']);
        $color = Color::factory()->create(['brand_id' => $brand->id, 'name' => 'Clear Transparent']);

        // The same product in a different pack size — should be linked.
        $sibling50 = Sku::factory()->create([
            'brand_id' => $brand->id, 'balloon_size_id' => $size->id, 'color_id' => $color->id,
            'default_count_per_bag' => 50, 'is_printed' => false,
        ]);
        // Same brand/size/colour but PRINTED — a different product, must NOT link.
        $printed = Sku::factory()->create([
            'brand_id' => $brand->id, 'balloon_size_id' => $size->id, 'color_id' => $color->id,
            'default_count_per_bag' => 50, 'is_printed' => true,
        ]);

        $distributor = Distributor::factory()->bigcommerce()->create();
        $proposal = DistributorCatalogProposal::factory()->create([
            'evidence' => [[
                'distributor_id' => $distributor->id, 'title' => 'x',
                'attributes' => ['Brand' => ['Kalisan'], 'Size' => ['260'], 'Color' => ['Clear'], 'Quantity' => ['100 ct']],
            ]],
            'proposed_count' => 100,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.distributors.proposals.approve', $proposal->id))
            ->assertSessionHas('success');

        $newSku = Sku::find($proposal->refresh()->resulting_sku_id);
        $linkedIds = $newSku->identicalSkus()->pluck('skus.id');

        $this->assertTrue($linkedIds->contains($sibling50->id));
        $this->assertFalse($linkedIds->contains($printed->id));
        // Symmetric: the sibling now points back.
        $this->assertTrue($sibling50->identicalSkus()->pluck('skus.id')->contains($newSku->id));
    }

    public function test_index_exposes_catalog_match_preview(): void
    {
        $brand = Brand::factory()->create(['name' => 'Kalisan']);
        $size = BalloonSize::factory()->create(['brand_id' => $brand->id, 'name' => '260K']);
        $color = Color::factory()->create(['brand_id' => $brand->id, 'name' => 'Clear Transparent']);
        Sku::factory()->create([
            'name' => 'Kalisan 260K Clear 50ct', 'brand_id' => $brand->id,
            'balloon_size_id' => $size->id, 'color_id' => $color->id,
            'default_count_per_bag' => 50, 'is_printed' => false,
        ]);

        $distributor = Distributor::factory()->bigcommerce()->create();
        DistributorCatalogProposal::factory()->create([
            'evidence' => [[
                'distributor_id' => $distributor->id, 'title' => 'x',
                'attributes' => ['Brand' => ['Kalisan'], 'Size' => ['260'], 'Color' => ['Clear'], 'Quantity' => ['100 ct']],
            ]],
            'proposed_count' => 100,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.distributors.proposals.index'))
            ->assertInertia(fn ($page) => $page
                ->where('proposals.data.0.catalog_match.available', true)
                ->where('proposals.data.0.catalog_match.exact', null)
                ->where('proposals.data.0.catalog_match.siblings.0.count', 50));
    }

    public function test_index_exposes_the_matcher_guess_for_review(): void
    {
        $brand = Brand::factory()->create(['name' => 'Kalisan']);
        BalloonSize::factory()->create(['brand_id' => $brand->id, 'name' => '260K']);
        Color::factory()->create(['brand_id' => $brand->id, 'name' => 'Clear Transparent']);
        $distributor = Distributor::factory()->bigcommerce()->create();

        DistributorCatalogProposal::factory()->create([
            'evidence' => [[
                'distributor_id' => $distributor->id,
                'title' => 'whatever',
                'attributes' => ['Brand' => ['Kalisan'], 'Size' => ['260'], 'Color' => ['Clear'], 'Quantity' => ['100 ct']],
            ]],
            'proposed_count' => 100,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.distributors.proposals.index'))
            ->assertInertia(fn ($page) => $page
                ->where('proposals.data.0.guess.available', true)
                ->where('proposals.data.0.guess.brand.selected.name', 'Kalisan')
                ->where('proposals.data.0.guess.balloon_size.selected.name', '260K')
                ->where('proposals.data.0.guess.color.selected.name', 'Clear Transparent')
                ->where('proposals.data.0.guess.count', 100));
    }

    public function test_index_surfaces_missing_reference_data_as_gaps(): void
    {
        $distributor = Distributor::factory()->bigcommerce()->create();

        // Two proposals naming a brand we don't have — should aggregate as one
        // brand gap with a count of 2.
        DistributorCatalogProposal::factory()->count(2)->create([
            'evidence' => [[
                'distributor_id' => $distributor->id,
                'title' => 'x',
                'attributes' => ['Brand' => ['Elitex'], 'Size' => ['260'], 'Color' => ['Red']],
            ]],
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.distributors.proposals.index'))
            ->assertInertia(fn ($page) => $page
                ->where('gaps.brands.0.value', 'Elitex')
                ->where('gaps.brands.0.count', 2));
    }

    public function test_approve_without_resolvable_attributes_warns_and_creates_no_sku(): void
    {
        $proposal = DistributorCatalogProposal::factory()->create([
            'proposed_name' => 'Unmappable mystery product',
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.distributors.proposals.approve', $proposal->id));

        $response->assertSessionHas('warning');

        $proposal->refresh();
        $this->assertSame(DistributorCatalogProposal::STATUS_APPROVED, $proposal->status);
        $this->assertNull($proposal->resulting_sku_id);
        $this->assertSame(0, Sku::count());
    }

    public function test_reject_marks_proposal_rejected(): void
    {
        $proposal = DistributorCatalogProposal::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.distributors.proposals.reject', $proposal->id))
            ->assertSessionHas('success');

        $proposal->refresh();
        $this->assertSame(DistributorCatalogProposal::STATUS_REJECTED, $proposal->status);
        $this->assertSame($this->admin->id, $proposal->reviewed_by);
    }

    public function test_map_to_existing_backfills_barcode_and_resolves_the_proposal(): void
    {
        // We already carry the product, but the existing SKU has no barcode.
        $existing = Sku::factory()->create(['upc' => null, 'ean' => null]);
        $distributor = Distributor::factory()->bigcommerce()->create();

        $proposal = DistributorCatalogProposal::factory()->create([
            'evidence' => [[
                'distributor_id' => $distributor->id,
                'raw_upc' => '8693296838147',           // 13-digit EAN-13
                'url' => 'https://larocks.com/p/1',
                'price' => 6.40,
            ]],
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.distributors.proposals.map-to-existing', $proposal->id), ['sku_id' => $existing->id])
            ->assertSessionHas('success');

        $existing->refresh();
        $this->assertSame('8693296838147', $existing->ean);

        // Audited as an admin action on the shared catalog (null business).
        $audit = BarcodeLinkAudit::where('sku_id', $existing->id)->sole();
        $this->assertSame('admin', $audit->source);
        $this->assertNull($audit->business_id);
        $this->assertSame($this->admin->id, $audit->user_id);

        // Distributor link attached + proposal resolved to the existing SKU.
        $this->assertDatabaseHas('distributor_sku_urls', [
            'distributor_id' => $distributor->id, 'sku_id' => $existing->id,
        ]);
        $proposal->refresh();
        $this->assertSame(DistributorCatalogProposal::STATUS_APPROVED, $proposal->status);
        $this->assertSame($existing->id, $proposal->resulting_sku_id);
    }

    public function test_map_to_existing_refuses_a_barcode_already_on_another_sku(): void
    {
        Sku::factory()->create(['ean' => '8693296838147']); // barcode taken
        $target = Sku::factory()->create(['upc' => null, 'ean' => null]);
        $distributor = Distributor::factory()->bigcommerce()->create();
        $proposal = DistributorCatalogProposal::factory()->create([
            'evidence' => [['distributor_id' => $distributor->id, 'raw_upc' => '8693296838147', 'url' => 'https://x/p']],
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.distributors.proposals.map-to-existing', $proposal->id), ['sku_id' => $target->id])
            ->assertSessionHas('warning');

        $this->assertNull($target->refresh()->ean);
        $this->assertSame(DistributorCatalogProposal::STATUS_PENDING, $proposal->refresh()->status);
    }

    public function test_reject_is_blocked_when_proposal_already_has_a_sku(): void
    {
        $sku = Sku::factory()->create();

        $proposal = DistributorCatalogProposal::factory()->autoApproved()->create([
            'resulting_sku_id' => $sku->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.distributors.proposals.reject', $proposal->id))
            ->assertSessionHas('warning');

        $proposal->refresh();
        // Still auto_approved — not rejected — and the SKU is untouched.
        $this->assertSame(DistributorCatalogProposal::STATUS_AUTO_APPROVED, $proposal->status);
        $this->assertDatabaseHas('skus', ['id' => $sku->id]);
    }

    public function test_update_persists_manual_attribute_mapping(): void
    {
        $balloonSize = BalloonSize::factory()->create();
        $color = Color::factory()->create(['brand_id' => $balloonSize->brand_id]);

        $proposal = DistributorCatalogProposal::factory()->create();

        $this->actingAs($this->admin)
            ->patch(route('admin.distributors.proposals.update', $proposal->id), [
                'proposed_brand_id' => $balloonSize->brand_id,
                'proposed_balloon_size_id' => $balloonSize->id,
                'proposed_color_id' => $color->id,
                'proposed_count' => 100,
            ])
            ->assertSessionHas('success');

        $proposal->refresh();
        $this->assertSame($balloonSize->brand_id, $proposal->proposed_brand_id);
        $this->assertSame($balloonSize->id, $proposal->proposed_balloon_size_id);
        $this->assertSame(100, $proposal->proposed_count);
        $this->assertSame($this->admin->id, $proposal->reviewed_by);
    }

    public function test_non_admin_cannot_reach_the_review_queue(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->get(route('admin.distributors.proposals.index'))
            ->assertForbidden();
    }

    public function test_index_show_page_includes_staged_counts(): void
    {
        $distributor = Distributor::factory()->shopify()->create();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.distributors.show', $distributor));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('stagedTotal')
            ->has('stagedWithUpc'));
    }

    public function test_pending_count_shared_via_inertia(): void
    {
        DistributorCatalogProposal::factory()->count(3)->create(['status' => 'pending']);
        DistributorCatalogProposal::factory()->create(['status' => 'approved']);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.distributors.proposals.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('proposals.data.0.status', 'pending')
            ->where('pendingCount', 3));
    }
}
