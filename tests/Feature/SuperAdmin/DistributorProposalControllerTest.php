<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\BalloonSize;
use App\Models\BarcodeLinkAudit;
use App\Models\Brand;
use App\Models\Color;
use App\Models\Distributor;
use App\Models\DistributorCatalogProposal;
use App\Models\DistributorLearnedAlias;
use App\Models\DistributorProduct;
use App\Models\PackagingType;
use App\Models\PrintColor;
use App\Models\PrintSide;
use App\Models\Sku;
use App\Models\Theme;
use App\Models\User;
use App\Services\Distributors\DistributorLearnedAliasStore;
use Database\Seeders\PackagingTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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

    /**
     * End-to-end reproduction of the production bug: a learned colour alias exists
     * for a coarse raw value ("Mustard" for this distributor+brand really meant a
     * specific unrelated shade on a DIFFERENT product once), but THIS product's own
     * title clearly names its actual shade. Promotion must use the title, not
     * silently trust the alias, because the alias's quality is 'learned' (not
     * 'exact') and the promoter's colour resolution defers to a clear title shade
     * whenever the structured guess isn't an unambiguous exact match.
     */
    public function test_approve_prefers_the_title_shade_over_a_mismatched_learned_alias(): void
    {
        $brand = Brand::factory()->create(['name' => 'Kalisan']);
        BalloonSize::factory()->create(['brand_id' => $brand->id, 'name' => '260K']);
        $wrongTarget = Color::factory()->create(['brand_id' => $brand->id, 'name' => 'Pearl Blue']);
        $correctShade = Color::factory()->create(['brand_id' => $brand->id, 'name' => 'Pastel Matte Yellow']);
        $distributor = Distributor::factory()->create();

        // Taught once, from an unrelated product where the coarse raw value really
        // did mean Pearl Blue.
        app(DistributorLearnedAliasStore::class)
            ->record($distributor->id, 'color', $brand->id, 'Mustard', $wrongTarget->id, null, null);

        // A different product: same coarse raw colour, but its own title clearly
        // states its real shade.
        $proposal = DistributorCatalogProposal::factory()->create([
            'proposed_name' => '5-inch Deluxe Mustard Kalisan',
            'evidence' => [[
                'distributor_id' => $distributor->id,
                'title' => '5-inch Pastel Matte Yellow Kalisan',
                'attributes' => [
                    'Brand' => ['Kalisan'], 'Size' => ['260'], 'Color' => ['Mustard'],
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
            'color_id' => $correctShade->id,
        ]);
    }

    public function test_approve_sets_packaging_from_the_structured_table(): void
    {
        $this->seed(PackagingTypeSeeder::class);
        $nozzleUp = PackagingType::where('name', 'Nozzle Up')->sole();

        $brand = Brand::factory()->create(['name' => 'Kalisan']);
        BalloonSize::factory()->create(['brand_id' => $brand->id, 'name' => '160K']);
        Color::factory()->create(['brand_id' => $brand->id, 'name' => 'Clear Transparent']);
        $distributor = Distributor::factory()->bigcommerce()->create([
            'config' => ['attribute_aliases' => ['packaging' => ['Nozzle-Up' => 'Nozzle Up']]],
        ]);

        $proposal = DistributorCatalogProposal::factory()->create([
            'evidence' => [[
                'distributor_id' => $distributor->id, 'title' => 'x',
                'attributes' => [
                    'Brand' => ['Kalisan'], 'Size' => ['160'], 'Color' => ['Clear'],
                    'Quantity' => ['50 ct'], 'Package Type' => ['Q-Pak / Nozzle-Up'],
                ],
            ]],
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.distributors.proposals.approve', $proposal->id))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('skus', [
            'id' => $proposal->refresh()->resulting_sku_id,
            'packaging_id' => $nozzleUp->id,
        ]);
    }

    public function test_manual_packaging_override_wins_on_approve(): void
    {
        $this->seed(PackagingTypeSeeder::class);
        $nozzle = PackagingType::where('name', 'Nozzle Up')->sole();

        $brand = Brand::factory()->create(['name' => 'Kalisan']);
        BalloonSize::factory()->create(['brand_id' => $brand->id, 'name' => '260K']);
        Color::factory()->create(['brand_id' => $brand->id, 'name' => 'Clear Transparent']);
        $distributor = Distributor::factory()->bigcommerce()->create([
            'config' => ['attribute_aliases' => ['packaging' => ['Loose Bag (Regular)' => 'Loose']]],
        ]);

        // Structured table says Loose, but the admin corrects it to Nozzle Up.
        $proposal = DistributorCatalogProposal::factory()->create([
            'evidence' => [[
                'distributor_id' => $distributor->id, 'title' => 'x',
                'attributes' => [
                    'Brand' => ['Kalisan'], 'Size' => ['260'], 'Color' => ['Clear'],
                    'Quantity' => ['100 ct'], 'Package Type' => ['Loose Bag (Regular)'],
                ],
            ]],
        ]);

        $this->actingAs($this->admin)
            ->patch(route('admin.distributors.proposals.update', $proposal->id), ['proposed_packaging_id' => $nozzle->id])
            ->assertSessionHas('success');
        $this->assertSame($nozzle->id, $proposal->refresh()->proposed_packaging_id);

        $this->actingAs($this->admin)
            ->post(route('admin.distributors.proposals.approve', $proposal->id))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('skus', [
            'id' => $proposal->refresh()->resulting_sku_id,
            'packaging_id' => $nozzle->id,
        ]);
    }

    public function test_probe_maps_a_page_to_our_catalog_without_writing(): void
    {
        $this->seed(PackagingTypeSeeder::class);
        $brand = Brand::factory()->create(['name' => 'Kalisan']);
        BalloonSize::factory()->create(['brand_id' => $brand->id, 'name' => '260K']);
        Color::factory()->create(['brand_id' => $brand->id, 'name' => 'Clear Transparent']);

        $distributor = Distributor::factory()->bigcommerce()->create([
            'config' => [
                'extraction' => [
                    'attribute_table' => ['header_class' => 'productView-table-header', 'value_class' => 'productView-table-data'],
                    'required_labels' => ['Brand'],
                    'min_rows' => 3,
                ],
            ],
        ]);

        Http::fake(['*' => Http::response($this->larocksProductHtml())]);

        $this->actingAs($this->admin)
            ->post(route('admin.distributors.probe', $distributor->id), ['probe_url' => 'https://larocks.com/p/260k'])
            ->assertInertia(fn ($page) => $page
                ->component('SuperAdmin/Distributors/Show')
                ->where('probe.fetched', true)
                ->where('probe.extraction.ok', true)
                ->where('probe.match.brand.matched', 'Kalisan')
                ->where('probe.match.balloon_size.matched', '260K')
                ->where('probe.match.color.matched', 'Clear Transparent')
                ->where('probe.match.packaging.matched', 'Loose'));

        // Read-only: nothing staged.
        $this->assertSame(0, DistributorProduct::count());
    }

    private function larocksProductHtml(): string
    {
        return <<<'HTML'
        <div class="productView-table">
            <div class="productView-table-row"><div class="productView-table-header">Brand:</div><div class="productView-table-data">Kalisan</div></div>
            <div class="productView-table-row"><div class="productView-table-header">Industry:</div><div class="productView-table-data">Balloons</div></div>
            <div class="productView-table-row"><div class="productView-table-header">Balloon Material:</div><div class="productView-table-data">Latex</div></div>
            <div class="productView-table-row"><div class="productView-table-header">Size:</div><div class="productView-table-data">260</div></div>
            <div class="productView-table-row"><div class="productView-table-header">Color:</div><div class="productView-table-data">Clear</div></div>
            <div class="productView-table-row"><div class="productView-table-header">Package Type:</div><div class="productView-table-data">Loose Bag (Regular)</div></div>
            <div class="productView-table-row"><div class="productView-table-header">Quantity:</div><div class="productView-table-data">100 ct</div></div>
        </div>
        HTML;
    }

    public function test_index_provides_packaging_reference_options(): void
    {
        $this->seed(PackagingTypeSeeder::class);

        $this->actingAs($this->admin)
            ->get(route('admin.distributors.proposals.index'))
            ->assertInertia(fn ($page) => $page->has('references.packagingTypes', 4));
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

    public function test_guess_color_falls_back_to_the_title_shade_when_structured_is_coarse(): void
    {
        $brand = Brand::factory()->create(['name' => 'Sempertex']);
        Color::factory()->create(['brand_id' => $brand->id, 'name' => 'Neon Green']);
        Color::factory()->create(['brand_id' => $brand->id, 'name' => 'Pastel Green Tea']);
        $distributor = Distributor::factory()->bigcommerce()->create();

        DistributorCatalogProposal::factory()->create([
            'evidence' => [[
                'distributor_id' => $distributor->id,
                'title' => '11 Inch Round Pastel Green Tea Sempertex 100ct',
                // Coarse structured "Green" fuzzy-matches Neon Green; the title names the shade.
                'attributes' => ['Brand' => ['Sempertex'], 'Color' => ['Green']],
            ]],
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.distributors.proposals.index'))
            ->assertInertia(fn ($page) => $page
                ->where('proposals.data.0.guess.color.selected.name', 'Pastel Green Tea')
                ->where('proposals.data.0.guess.color.source', 'title'));
    }

    public function test_index_exposes_brand_and_state_facets(): void
    {
        $distributor = Distributor::factory()->bigcommerce()->create();
        $make = fn (array $extra) => DistributorCatalogProposal::factory()->create($extra + [
            'evidence' => [['distributor_id' => $distributor->id, 'title' => 'x']],
        ]);

        $make(['resolved_brand_name' => 'Sempertex', 'resolution_state' => 'full']);
        $make(['resolved_brand_name' => 'Sempertex', 'resolution_state' => 'partial']);
        $make(['resolved_brand_name' => 'Britetex', 'resolution_state' => 'full']);
        $make(['resolved_brand_name' => null, 'resolution_state' => 'no_brand']);

        $this->actingAs($this->admin)
            ->get(route('admin.distributors.proposals.index'))
            ->assertInertia(fn ($page) => $page
                ->where('facets.brands.0.name', 'Sempertex') // most proposals
                ->where('facets.brands.0.count', 2)
                ->where('facets.states.full', 2)
                ->where('facets.states.partial', 1)
                ->where('facets.states.no_brand', 1));
    }

    public function test_pending_proposals_sort_fully_resolved_first(): void
    {
        $distributor = Distributor::factory()->bigcommerce()->create();
        $make = fn (string $state) => DistributorCatalogProposal::factory()->create([
            'resolution_state' => $state,
            'evidence' => [['distributor_id' => $distributor->id, 'title' => 'x']],
        ]);

        $make('no_brand');
        $make('partial');
        $make('full');

        $this->actingAs($this->admin)
            ->get(route('admin.distributors.proposals.index'))
            ->assertInertia(fn ($page) => $page
                ->where('proposals.data.0.status', 'pending')
                ->where('proposals.data.0.id', DistributorCatalogProposal::where('resolution_state', 'full')->value('id'))
                ->where('proposals.data.2.id', DistributorCatalogProposal::where('resolution_state', 'no_brand')->value('id')));
    }

    public function test_brand_filter_matches_the_resolved_brand_exactly(): void
    {
        $distributor = Distributor::factory()->bigcommerce()->create();
        DistributorCatalogProposal::factory()->create(['resolved_brand_name' => 'Sempertex', 'evidence' => [['distributor_id' => $distributor->id]]]);
        DistributorCatalogProposal::factory()->create(['resolved_brand_name' => 'Britetex', 'evidence' => [['distributor_id' => $distributor->id]]]);

        $this->actingAs($this->admin)
            ->get(route('admin.distributors.proposals.index', ['brand' => 'Britetex']))
            ->assertInertia(fn ($page) => $page
                ->has('proposals.data', 1)
                ->where('proposals.data.0.resolved_brand_name', 'Britetex'));
    }

    public function test_index_surfaces_missing_reference_data_as_gaps(): void
    {
        $distributor = Distributor::factory()->bigcommerce()->create();

        // Two proposals naming a brand we don't have — should aggregate as one
        // brand gap with a count of 2. Gaps now read the resolution stamped at
        // cluster time, so an unresolved brand is stored as a {value} detail.
        DistributorCatalogProposal::factory()->count(2)->create([
            'resolution_state' => DistributorCatalogProposal::RESOLUTION_NO_BRAND,
            'resolution' => ['brand' => ['value' => 'Elitex']],
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

    /**
     * The target predates the barcode match, so it may still carry a stale
     * internal warehouse SKU from before distributor evidence existed for it. The
     * proposal's own warehouse SKU is the multi-distributor consensus (computed at
     * cluster time) — mapping must sync it onto the target, not leave the old one.
     */
    public function test_map_to_existing_syncs_the_consensus_warehouse_sku_onto_the_target(): void
    {
        $existing = Sku::factory()->create(['upc' => null, 'ean' => null, 'warehouse_sku' => '20018141']);
        $distributor = Distributor::factory()->bigcommerce()->create();

        $proposal = DistributorCatalogProposal::factory()->create([
            'proposed_warehouse_sku' => '54529',
            'evidence' => [[
                'distributor_id' => $distributor->id,
                'raw_upc' => '8693296838147',
                'url' => 'https://larocks.com/p/1',
            ]],
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.distributors.proposals.map-to-existing', $proposal->id), ['sku_id' => $existing->id])
            ->assertSessionHas('success');

        $this->assertSame('54529', $existing->fresh()->warehouse_sku);
    }

    public function test_map_to_existing_leaves_warehouse_sku_alone_when_the_proposal_has_none(): void
    {
        $existing = Sku::factory()->create(['upc' => null, 'ean' => null, 'warehouse_sku' => '20018141']);
        $distributor = Distributor::factory()->bigcommerce()->create();

        $proposal = DistributorCatalogProposal::factory()->create([
            'proposed_warehouse_sku' => null,
            'evidence' => [[
                'distributor_id' => $distributor->id,
                'raw_upc' => '8693296838147',
                'url' => 'https://larocks.com/p/1',
            ]],
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.distributors.proposals.map-to-existing', $proposal->id), ['sku_id' => $existing->id])
            ->assertSessionHas('success');

        $this->assertSame('20018141', $existing->fresh()->warehouse_sku);
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

    public function test_update_persists_print_classification_and_approve_creates_a_printed_sku(): void
    {
        $balloonSize = BalloonSize::factory()->create();
        $color = Color::factory()->create(['brand_id' => $balloonSize->brand_id]);
        $theme = Theme::factory()->create(['name' => 'Emoji']);
        $printColor = PrintColor::factory()->create(['name' => 'Multi']);
        $printSide = PrintSide::factory()->create(['name' => 'Single Side']);

        $proposal = DistributorCatalogProposal::factory()->create();

        $this->actingAs($this->admin)
            ->patch(route('admin.distributors.proposals.update', $proposal->id), [
                'proposed_brand_id' => $balloonSize->brand_id,
                'proposed_balloon_size_id' => $balloonSize->id,
                'proposed_color_id' => $color->id,
                'proposed_is_printed' => true,
                'proposed_theme_ids' => [$theme->id],
                'proposed_print_color_ids' => [$printColor->id],
                'proposed_print_side_ids' => [$printSide->id],
            ])
            ->assertSessionHas('success');

        $proposal->refresh();
        $this->assertTrue($proposal->proposed_is_printed);
        $this->assertSame([$theme->id], $proposal->proposed_theme_ids);

        $this->actingAs($this->admin)
            ->post(route('admin.distributors.proposals.approve', $proposal->id))
            ->assertSessionHas('success');

        $sku = Sku::find($proposal->refresh()->resulting_sku_id);
        $this->assertNotNull($sku);
        $this->assertTrue((bool) $sku->is_printed);
        $this->assertTrue($sku->themes()->where('themes.id', $theme->id)->exists());
        $this->assertTrue($sku->printColors()->where('print_colors.id', $printColor->id)->exists());
        $this->assertTrue($sku->printSides()->where('print_sides.id', $printSide->id)->exists());
    }

    public function test_update_persists_an_edited_proposed_name(): void
    {
        $proposal = DistributorCatalogProposal::factory()->create(['proposed_name' => 'Original name']);

        $this->actingAs($this->admin)
            ->patch(route('admin.distributors.proposals.update', $proposal->id), [
                'proposed_name' => 'Edited name',
            ])
            ->assertSessionHas('success');

        $this->assertSame('Edited name', $proposal->fresh()->proposed_name);
    }

    public function test_update_without_a_name_keeps_the_existing_one(): void
    {
        $proposal = DistributorCatalogProposal::factory()->create(['proposed_name' => 'Keep me']);

        $this->actingAs($this->admin)
            ->patch(route('admin.distributors.proposals.update', $proposal->id), [
                'proposed_count' => 25,
            ])
            ->assertSessionHas('success');

        $this->assertSame('Keep me', $proposal->fresh()->proposed_name);
    }

    public function test_update_saves_a_reviewer_note_and_learns_an_alias(): void
    {
        $brand = Brand::factory()->create(['name' => 'Kalisan']);
        $color = Color::factory()->create(['brand_id' => $brand->id, 'name' => 'Fashion Red']);
        $distributor = Distributor::factory()->create();

        $proposal = DistributorCatalogProposal::factory()->create([
            'evidence' => [[
                'distributor_id' => $distributor->id,
                'title' => 'Kalisan 260 modeling balloons',
                'attributes' => ['Brand' => ['Kalisan'], 'Color' => ['Red Fashion']],
            ]],
        ]);

        $this->actingAs($this->admin)
            ->patch(route('admin.distributors.proposals.update', $proposal->id), [
                'proposed_brand_id' => $brand->id,
                'proposed_color_id' => $color->id,
                'note' => 'Word order is reversed for this distributor.',
                'touched_fields' => ['brand', 'color'],
            ])
            ->assertSessionHas('success');

        $this->assertSame('Word order is reversed for this distributor.', $proposal->fresh()->note);

        $alias = DistributorLearnedAlias::query()
            ->where('distributor_id', $distributor->id)
            ->where('attribute', 'color')
            ->first();

        $this->assertNotNull($alias);
        $this->assertSame($color->id, $alias->catalog_id);
        $this->assertSame('Word order is reversed for this distributor.', $alias->note);
    }

    /**
     * The production bug this whole gate exists to prevent: the edit form
     * pre-fills proposed_color_id with the matcher's own live guess and submits
     * the whole row on any save, so a PATCH can carry a non-null proposed_color_id
     * purely because some unrelated field (here, the note) was edited — never
     * because the admin looked at or chose that colour. Omitting touched_fields
     * (as an untouched select would) must not teach an alias from it.
     */
    public function test_update_does_not_learn_an_alias_for_an_untouched_color_field(): void
    {
        $brand = Brand::factory()->create(['name' => 'Kalisan']);
        $color = Color::factory()->create(['brand_id' => $brand->id, 'name' => 'Fashion Red']);
        $distributor = Distributor::factory()->create();

        $proposal = DistributorCatalogProposal::factory()->create([
            'evidence' => [[
                'distributor_id' => $distributor->id,
                'title' => 'Kalisan 260 modeling balloons',
                'attributes' => ['Brand' => ['Kalisan'], 'Color' => ['Red Fashion']],
            ]],
        ]);

        $this->actingAs($this->admin)
            ->patch(route('admin.distributors.proposals.update', $proposal->id), [
                'proposed_brand_id' => $brand->id,
                'proposed_color_id' => $color->id,
                'note' => 'Just a note — the colour field was never touched.',
                // No touched_fields: matches an untouched select carried along by
                // the pre-filled form.
            ])
            ->assertSessionHas('success');

        $this->assertSame($color->id, $proposal->fresh()->proposed_color_id);
        $this->assertSame(
            0,
            DistributorLearnedAlias::where('distributor_id', $distributor->id)->where('attribute', 'color')->count(),
        );
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
