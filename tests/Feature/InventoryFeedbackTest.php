<?php

namespace Tests\Feature;

use App\Enums\FeedbackStatus;
use App\Models\Bin;
use App\Models\Business;
use App\Models\Location;
use App\Models\Membership;
use App\Models\Sku;
use App\Models\SkuFeedback;
use App\Models\StockLevel;
use App\Models\User;
use App\Scopes\BusinessScope;
use App\Support\BusinessContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryFeedbackTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Business $business;

    private Bin $bin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);

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

        BusinessContext::set($this->business->id);
    }

    protected function tearDown(): void
    {
        BusinessContext::clear();
        parent::tearDown();
    }

    private function skuInInventory(array $attrs = []): Sku
    {
        $sku = Sku::factory()->create($attrs);

        StockLevel::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'sku_id' => $sku->id,
            'bin_id' => $this->bin->id,
            'full_bags' => 1,
            'open_bags' => 0,
        ]);

        return $sku;
    }

    public function test_submitting_feedback_records_a_report_with_snapshot_and_context(): void
    {
        $sku = $this->skuInInventory(['name' => 'Round 11" Red']);

        $this->actingAs($this->owner)
            ->from(route('inventory.sku.show', $sku->id))
            ->post(route('inventory.sku.feedback', $sku->id), [
                'field' => 'color',
                'current_value' => 'Fashion Red',
                'suggested_value' => 'Crystal Red',
                'note' => 'The bag says Crystal.',
            ])
            ->assertRedirect(route('inventory.sku.show', $sku->id))
            ->assertSessionHas('success');

        $feedback = SkuFeedback::query()->firstOrFail();

        $this->assertSame($sku->id, $feedback->sku_id);
        $this->assertSame('Round 11" Red', $feedback->sku_name);
        $this->assertSame('color', $feedback->field);
        $this->assertSame('Fashion Red', $feedback->current_value);
        $this->assertSame('Crystal Red', $feedback->suggested_value);
        $this->assertSame('The bag says Crystal.', $feedback->note);
        $this->assertSame(FeedbackStatus::Open, $feedback->status);
        $this->assertSame($this->owner->id, $feedback->user_id);
        $this->assertSame($this->business->id, $feedback->business_id);
    }

    public function test_feedback_accepts_a_note_only_report(): void
    {
        $sku = $this->skuInInventory();

        $this->actingAs($this->owner)
            ->post(route('inventory.sku.feedback', $sku->id), [
                'field' => 'image',
                'note' => 'The photo shows the wrong product.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('sku_feedback', 1);
    }

    public function test_feedback_requires_some_content(): void
    {
        $sku = $this->skuInInventory();

        $this->actingAs($this->owner)
            ->post(route('inventory.sku.feedback', $sku->id), [
                'field' => 'color',
            ])
            ->assertSessionHasErrors(['suggested_value', 'note']);

        $this->assertDatabaseCount('sku_feedback', 0);
    }

    public function test_feedback_rejects_an_unknown_field(): void
    {
        $sku = $this->skuInInventory();

        $this->actingAs($this->owner)
            ->post(route('inventory.sku.feedback', $sku->id), [
                'field' => 'price',
                'suggested_value' => '5.00',
            ])
            ->assertSessionHasErrors('field');

        $this->assertDatabaseCount('sku_feedback', 0);
    }

    public function test_feedback_is_404_when_the_sku_is_not_in_inventory(): void
    {
        $sku = Sku::factory()->create();

        $this->actingAs($this->owner)
            ->post(route('inventory.sku.feedback', $sku->id), [
                'field' => 'color',
                'suggested_value' => 'Blue',
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('sku_feedback', 0);
    }

    public function test_detail_page_exposes_the_matching_barcode_on_the_sku_prop(): void
    {
        $sku = $this->skuInInventory(['upc' => '012345678905', 'ean' => null]);

        $this->actingAs($this->owner)
            ->get(route('inventory.sku.show', $sku->id))
            ->assertInertia(fn ($page) => $page
                ->where('sku.upc', '012345678905')
                ->where('sku.ean', null)
            );
    }
}
