<?php

namespace Tests\Feature\Distributors;

use App\Models\BalloonSize;
use App\Models\Brand;
use App\Models\Color;
use App\Models\Distributor;
use App\Models\DistributorCatalogProposal;
use App\Models\DistributorLearnedAlias;
use App\Models\User;
use App\Services\Distributors\DistributorProposalReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 1 of the resolution learning layer: an admin's correction in the review
 * queue becomes a learned alias that resolves the same raw distributor value for
 * every future (and other pending) proposal, with no config change.
 */
class DistributorLearnedAliasCaptureTest extends TestCase
{
    use RefreshDatabase;

    private DistributorProposalReviewService $service;

    private User $admin;

    private Brand $kalisan;

    private Distributor $distributor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(DistributorProposalReviewService::class);
        $this->admin = User::factory()->create(['admin_level' => 'super_admin']);
        $this->kalisan = Brand::factory()->create(['name' => 'Kalisan']);
        $this->distributor = Distributor::factory()->create();
    }

    /**
     * @param  array<string, array<int, string>>  $attributes
     * @return array<string, mixed>
     */
    private function member(array $attributes, string $title = 'Kalisan 260 modeling balloons'): array
    {
        return [
            'distributor_id' => $this->distributor->id,
            'title' => $title,
            'attributes' => $attributes,
        ];
    }

    public function test_editing_a_proposal_captures_a_learned_alias(): void
    {
        $fashionRed = Color::factory()->create(['brand_id' => $this->kalisan->id, 'name' => 'Fashion Red']);
        Color::factory()->create(['brand_id' => $this->kalisan->id, 'name' => 'Red']);

        $proposal = DistributorCatalogProposal::factory()->create([
            'evidence' => [$this->member(['Brand' => ['Kalisan'], 'Color' => ['Red Fashion']])],
        ]);

        $this->service->edit($proposal, [
            'proposed_brand_id' => $this->kalisan->id,
            'proposed_color_id' => $fashionRed->id,
            'note' => 'Finish/colour word order is reversed here.',
        ], $this->admin->id);

        $alias = DistributorLearnedAlias::query()
            ->where('distributor_id', $this->distributor->id)
            ->where('attribute', 'color')
            ->first();

        $this->assertNotNull($alias);
        $this->assertSame($this->kalisan->id, $alias->brand_id);
        $this->assertSame('red fashion', $alias->raw_value_normalized);
        $this->assertSame($fashionRed->id, $alias->catalog_id);
        $this->assertSame('Finish/colour word order is reversed here.', $alias->note);
        $this->assertSame($this->admin->id, $alias->created_by);
    }

    public function test_a_learned_alias_resolves_a_later_proposal_at_queue_render(): void
    {
        $fashionRed = Color::factory()->create(['brand_id' => $this->kalisan->id, 'name' => 'Fashion Red']);
        Color::factory()->create(['brand_id' => $this->kalisan->id, 'name' => 'Red']);

        $taught = DistributorCatalogProposal::factory()->create([
            'evidence' => [$this->member(['Brand' => ['Kalisan'], 'Color' => ['Red Fashion']])],
        ]);

        $this->service->edit($taught, [
            'proposed_brand_id' => $this->kalisan->id,
            'proposed_color_id' => $fashionRed->id,
        ], $this->admin->id);

        // A brand-new proposal carrying the same raw value, with no manual mapping.
        $later = DistributorCatalogProposal::factory()->create([
            'evidence' => [$this->member(['Brand' => ['Kalisan'], 'Color' => ['Red Fashion']])],
        ]);

        $presented = $this->service->paginate([])->getCollection()
            ->firstWhere('id', $later->id);

        $this->assertSame($fashionRed->id, $presented['guess']['color']['selected']['id']);
        $this->assertSame('exact', $presented['guess']['color']['quality']);
    }

    public function test_capturing_an_alias_restamps_a_sibling_pending_proposals_facets(): void
    {
        $size = BalloonSize::factory()->create(['brand_id' => $this->kalisan->id, 'name' => '260K']);
        $fashionRed = Color::factory()->create(['brand_id' => $this->kalisan->id, 'name' => 'Fashion Red']);

        $attributes = ['Brand' => ['Kalisan'], 'Size' => ['260'], 'Color' => ['Zztop Special']];

        // A sibling already stamped at cluster time: brand + size resolve, but the
        // unknown colour leaves it partial.
        $sibling = DistributorCatalogProposal::factory()->create([
            'evidence' => [$this->member($attributes)],
            'resolved_brand_id' => $this->kalisan->id,
            'resolved_brand_name' => 'Kalisan',
            'resolution_state' => DistributorCatalogProposal::RESOLUTION_PARTIAL,
        ]);

        $taught = DistributorCatalogProposal::factory()->create([
            'evidence' => [$this->member($attributes)],
        ]);

        $this->service->edit($taught, [
            'proposed_brand_id' => $this->kalisan->id,
            'proposed_balloon_size_id' => $size->id,
            'proposed_color_id' => $fashionRed->id,
        ], $this->admin->id);

        // The colour now resolves via the learned alias, so the sibling's stored
        // facet state flips to full without a re-cluster.
        $this->assertSame(
            DistributorCatalogProposal::RESOLUTION_FULL,
            $sibling->fresh()->resolution_state,
        );
    }

    public function test_approving_a_later_proposal_promotes_with_the_learned_colour(): void
    {
        $size = BalloonSize::factory()->create(['brand_id' => $this->kalisan->id, 'name' => '260K']);
        $fashionRed = Color::factory()->create(['brand_id' => $this->kalisan->id, 'name' => 'Fashion Red']);
        Color::factory()->create(['brand_id' => $this->kalisan->id, 'name' => 'Red']);

        $attributes = ['Brand' => ['Kalisan'], 'Size' => ['260'], 'Color' => ['Red Fashion']];

        $taught = DistributorCatalogProposal::factory()->create([
            'evidence' => [$this->member($attributes)],
        ]);
        $this->service->edit($taught, [
            'proposed_brand_id' => $this->kalisan->id,
            'proposed_color_id' => $fashionRed->id,
        ], $this->admin->id);

        // A second proposal with no manual mapping — approval must re-resolve via
        // the learned alias, not the fuzzy "Red", when the promoter builds the SKU.
        $later = DistributorCatalogProposal::factory()->create([
            'evidence' => [$this->member($attributes)],
        ]);

        $result = $this->service->approve($later, $this->admin->id);

        $this->assertNotNull($later->fresh()->resulting_sku_id);
        $this->assertDatabaseHas('skus', [
            'id' => $later->fresh()->resulting_sku_id,
            'balloon_size_id' => $size->id,
            'color_id' => $fashionRed->id,
        ]);
    }

    public function test_a_brand_scoped_alias_does_not_apply_to_another_brand(): void
    {
        $sempertex = Brand::factory()->create(['name' => 'Sempertex']);
        $kalisanFashionRed = Color::factory()->create(['brand_id' => $this->kalisan->id, 'name' => 'Fashion Red']);
        $sempertexRed = Color::factory()->create(['brand_id' => $sempertex->id, 'name' => 'Red']);

        $taught = DistributorCatalogProposal::factory()->create([
            'evidence' => [$this->member(['Brand' => ['Kalisan'], 'Color' => ['Red Fashion']])],
        ]);

        $this->service->edit($taught, [
            'proposed_brand_id' => $this->kalisan->id,
            'proposed_color_id' => $kalisanFashionRed->id,
        ], $this->admin->id);

        // Same raw colour, different brand — the Kalisan-scoped alias must not fire.
        $sempertexProposal = DistributorCatalogProposal::factory()->create([
            'evidence' => [$this->member(['Brand' => ['Sempertex'], 'Color' => ['Red Fashion']])],
        ]);

        $presented = $this->service->paginate([])->getCollection()
            ->firstWhere('id', $sempertexProposal->id);

        $this->assertNotSame($kalisanFashionRed->id, $presented['guess']['color']['selected']['id'] ?? null);
    }
}
