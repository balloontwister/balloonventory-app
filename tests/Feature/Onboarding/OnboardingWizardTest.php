<?php

namespace Tests\Feature\Onboarding;

use App\Enums\StockDirection;
use App\Models\BalloonSize;
use App\Models\Bin;
use App\Models\Brand;
use App\Models\Business;
use App\Models\Color;
use App\Models\ColorFamily;
use App\Models\Location;
use App\Models\Membership;
use App\Models\Shape;
use App\Models\Size;
use App\Models\Sku;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\User;
use App\Scopes\BusinessScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingWizardTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, Size> */
    private array $sizes = [];

    /** @var array<string, Shape> */
    private array $shapes = [];

    public function test_creating_a_business_redirects_to_the_wizard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/onboarding/create-business', ['name' => 'Twisty Balloons'])
            ->assertRedirect(route('onboarding.wizard'));
    }

    public function test_wizard_page_renders_with_brand_and_role_options(): void
    {
        Brand::factory()->create(['name' => 'Sempertex']);
        [$user, $business] = $this->owner();

        $this->actingAs($user)
            ->withSession(['current_business_id' => $business->id])
            ->get(route('onboarding.wizard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Onboarding/Wizard')
                ->where('business.name', $business->name)
                ->has('brands')
                ->where('roles', ['twister', 'decorator', 'retailer'])
                ->has('supportedLocales'));
    }

    public function test_completing_the_wizard_seeds_samples_and_marks_the_business(): void
    {
        $stx = Brand::factory()->create(['name' => 'Sempertex']);
        // Two twister.json rows have a real catalog match; the rest become gaps.
        $white = $this->sku($stx, '260', 'Non-round', 'Fashion White', 50);
        $red = $this->sku($stx, '260', 'Non-round', 'Fashion Red', 50);

        [$user, $business] = $this->owner();

        $this->actingAs($user)
            ->withSession(['current_business_id' => $business->id])
            ->post(route('onboarding.wizard.complete'), [
                'role' => 'twister',
                'brands' => ['Sempertex'],
                'locale' => 'es',
                'badge_color' => '#FF8800',
            ])
            ->assertRedirect(route('dashboard'));

        $business->refresh();
        $this->assertSame('twister', $business->business_type);
        $this->assertNotNull($business->onboarding_completed_at);
        $this->assertSame('twister', $business->onboarding_answers['role']);

        // Owner preferences + business accent color applied.
        $this->assertSame('es', $user->refresh()->locale);
        $this->assertSame('#FF8800', $business->refresh()->color);

        // Sample stock seeded as real inventory, flagged is_sample.
        $levels = StockLevel::withoutGlobalScope(BusinessScope::class)
            ->where('business_id', $business->id)->get();
        $this->assertCount(2, $levels);
        $this->assertTrue($levels->every(fn ($l) => $l->is_sample));
        $this->assertSame(4, $levels->firstWhere('sku_id', $white->id)->full_bags); // white-260 bags
        $this->assertSame(3, $levels->firstWhere('sku_id', $red->id)->full_bags);   // red-260 bags

        $this->assertSame(2, StockMovement::withoutGlobalScope(BusinessScope::class)
            ->where('business_id', $business->id)
            ->where('is_sample', true)
            ->where('direction', StockDirection::In)
            ->count());
    }

    public function test_samples_are_distributed_across_bins_by_color_family(): void
    {
        $stx = Brand::factory()->create(['name' => 'Sempertex']);
        $whites = ColorFamily::factory()->create(['name' => 'Whites', 'sort_order' => 1]);
        $reds = ColorFamily::factory()->create(['name' => 'Reds', 'sort_order' => 5]);

        // Two twister.json rows with a real match, in two different families.
        $white = $this->sku($stx, '260', 'Non-round', 'Fashion White', 50, $whites);
        $red = $this->sku($stx, '260', 'Non-round', 'Fashion Red', 50, $reds);

        [$user, $business] = $this->owner();

        $this->actingAs($user)
            ->withSession(['current_business_id' => $business->id])
            ->post(route('onboarding.wizard.complete'), [
                'role' => 'twister',
                'brands' => ['Sempertex'],
                'locale' => 'en',
                'locations' => [['name' => 'Studio', 'bins' => ['Bin A', 'Bin B']]],
            ])
            ->assertRedirect(route('dashboard'));

        $whiteBin = StockLevel::withoutGlobalScope(BusinessScope::class)
            ->where('sku_id', $white->id)->value('bin_id');
        $redBin = StockLevel::withoutGlobalScope(BusinessScope::class)
            ->where('sku_id', $red->id)->value('bin_id');

        $this->assertNotNull($whiteBin);
        $this->assertNotNull($redBin);
        // Two colour families across two bins → each lands in its own bin.
        $this->assertNotSame($whiteBin, $redBin);
    }

    public function test_completing_the_wizard_renames_default_and_creates_locations_and_bins(): void
    {
        [$user, $business] = $this->owner();

        $this->actingAs($user)
            ->withSession(['current_business_id' => $business->id])
            ->post(route('onboarding.wizard.complete'), [
                'role' => 'decorator',
                'brands' => [],
                'locale' => 'en',
                'locations' => [
                    ['name' => 'Studio', 'bins' => ['Shelf A', 'Shelf B']],
                    ['name' => 'Garage', 'bins' => ['Tote 1']],
                ],
            ])
            ->assertRedirect(route('dashboard'));

        // The seeded Default location/bin were renamed to the first entry.
        $default = Location::withoutGlobalScope(BusinessScope::class)
            ->where('business_id', $business->id)->where('is_default', true)->first();
        $this->assertSame('Studio', $default->name);

        $defaultBin = Bin::withoutGlobalScope(BusinessScope::class)
            ->where('business_id', $business->id)->where('is_default', true)->first();
        $this->assertSame('Shelf A', $defaultBin->name);

        $this->assertSame(2, Location::withoutGlobalScope(BusinessScope::class)
            ->where('business_id', $business->id)->count());
        $this->assertSame(3, Bin::withoutGlobalScope(BusinessScope::class)
            ->where('business_id', $business->id)->count());
    }

    public function test_skipping_marks_onboarding_completed_without_seeding(): void
    {
        [$user, $business] = $this->owner();

        $this->actingAs($user)
            ->withSession(['current_business_id' => $business->id])
            ->post(route('onboarding.wizard.skip'))
            ->assertRedirect(route('dashboard'));

        $this->assertNotNull($business->refresh()->onboarding_completed_at);
        $this->assertSame(0, StockLevel::withoutGlobalScope(BusinessScope::class)
            ->where('business_id', $business->id)->count());
    }

    public function test_clear_samples_removes_untouched_but_keeps_touched(): void
    {
        [$user, $business] = $this->owner();
        $bin = $this->defaultBin($business);

        $stx = Brand::factory()->create(['name' => 'Sempertex']);
        $untouched = $this->sku($stx, '260', 'Non-round', 'White', 50);
        $touched = $this->sku($stx, '260', 'Non-round', 'Red', 50);

        $this->sampleLevel($business, $untouched, $bin, $user);
        $this->sampleLevel($business, $touched, $bin, $user);

        // A real (non-sample) movement on the touched SKU.
        StockMovement::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $business->id, 'sku_id' => $touched->id, 'bin_id' => $bin->id,
            'user_id' => $user->id, 'direction' => StockDirection::In,
            'full_bags_change' => 1, 'open_bags_change' => 0, 'is_sample' => false,
        ]);

        $this->actingAs($user)
            ->withSession(['current_business_id' => $business->id])
            ->post(route('onboarding.samples.clear'))
            ->assertRedirect();

        // Untouched sample is gone; touched sample is kept and promoted to real.
        $this->assertNull(StockLevel::withoutGlobalScope(BusinessScope::class)
            ->where('sku_id', $untouched->id)->first());

        $keptLevel = StockLevel::withoutGlobalScope(BusinessScope::class)
            ->where('sku_id', $touched->id)->first();
        $this->assertNotNull($keptLevel);
        $this->assertFalse($keptLevel->is_sample);
    }

    /**
     * @return array{0: User, 1: Business}
     */
    private function owner(): array
    {
        $user = User::factory()->create();
        $business = Business::factory()->create();

        Membership::create([
            'user_id' => $user->id,
            'business_id' => $business->id,
            'role' => 'owner',
            'business_badge_color' => '#6366F1',
            'joined_at' => now(),
        ]);

        return [$user, $business];
    }

    private function defaultBin(Business $business): Bin
    {
        $location = Location::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $business->id, 'name' => 'Default', 'is_default' => true,
        ]);

        return Bin::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $business->id, 'location_id' => $location->id,
            'name' => 'Default', 'is_default' => true,
        ]);
    }

    private function sampleLevel(Business $business, Sku $sku, Bin $bin, User $user): void
    {
        StockLevel::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $business->id, 'sku_id' => $sku->id, 'bin_id' => $bin->id,
            'full_bags' => 2, 'open_bags' => 0, 'is_sample' => true, 'last_movement_at' => now(),
        ]);

        StockMovement::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $business->id, 'sku_id' => $sku->id, 'bin_id' => $bin->id,
            'user_id' => $user->id, 'direction' => StockDirection::In,
            'full_bags_change' => 2, 'open_bags_change' => 0, 'is_sample' => true,
        ]);
    }

    private function sku(Brand $brand, string $size, string $shape, string $color, int $count, ?ColorFamily $family = null): Sku
    {
        $balloonSize = BalloonSize::factory()->create([
            'brand_id' => $brand->id,
            'size_id' => $this->sizeRow($size)->id,
            'shape_id' => $this->shapeRow($shape)->id,
        ]);

        $colorAttrs = ['brand_id' => $brand->id, 'name' => $color];
        if ($family !== null) {
            $colorAttrs['color_family_id'] = $family->id;
        }
        $colorModel = Color::factory()->create($colorAttrs);

        return Sku::factory()->create([
            'brand_id' => $brand->id,
            'balloon_size_id' => $balloonSize->id,
            'color_id' => $colorModel->id,
            'default_count_per_bag' => $count,
            'owned_by_business_id' => null,
        ]);
    }

    private function sizeRow(string $name): Size
    {
        return $this->sizes[$name] ??= Size::factory()->create(['name' => $name]);
    }

    private function shapeRow(string $name): Shape
    {
        return $this->shapes[$name] ??= Shape::factory()->create(['name' => $name]);
    }
}
