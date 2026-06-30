<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Membership;
use App\Models\User;
use App\Scopes\BusinessScope;
use App\Support\BusinessContext;
use Database\Seeders\EmailTemplateSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class MembershipLeaveAndCreateBusinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(EmailTemplateSeeder::class);
    }

    private function makeBusinessWithOwner(): array
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();
        Membership::create([
            'user_id' => $owner->id,
            'business_id' => $business->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        return [$owner, $business];
    }

    private function addMember(Business $business, string $role): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $membership = Membership::create([
            'user_id' => $user->id,
            'business_id' => $business->id,
            'role' => $role,
            'joined_at' => now(),
        ]);

        return [$user, $membership];
    }

    public function test_a_member_can_leave_their_current_business(): void
    {
        [, $business] = $this->makeBusinessWithOwner();
        [$artist, $membership] = $this->addMember($business, 'staff');
        BusinessContext::set($business->id);

        $this->actingAs($artist)
            ->from(route('settings.businesses'))
            ->delete(route('memberships.leave', ['membership' => $membership->id]))
            ->assertRedirect(route('account.index'));

        $this->assertSoftDeleted('memberships', ['id' => $membership->id]);
    }

    public function test_a_sole_owner_cannot_leave(): void
    {
        [$owner, $business] = $this->makeBusinessWithOwner();
        $membership = Membership::withoutGlobalScope(BusinessScope::class)
            ->where('business_id', $business->id)
            ->where('user_id', $owner->id)
            ->first();
        BusinessContext::set($business->id);

        $this->actingAs($owner)
            ->from(route('settings.businesses'))
            ->delete(route('memberships.leave', ['membership' => $membership->id]))
            ->assertRedirect(route('settings.businesses'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('memberships', [
            'id' => $membership->id,
            'deleted_at' => null,
        ]);
    }

    public function test_my_business_hub_exposes_leave_and_create_flags_to_a_member(): void
    {
        [, $business] = $this->makeBusinessWithOwner();
        [$artist, $membership] = $this->addMember($business, 'staff');
        BusinessContext::set($business->id);

        $this->actingAs($artist)
            ->get(route('settings.businesses'))
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->component('Settings/Businesses')
                    ->where('can.leave', true)
                    ->where('can.createBusiness', true)
                    ->where('ownMembershipId', $membership->id),
            );
    }

    public function test_my_business_hub_hides_leave_for_a_sole_owner(): void
    {
        [$owner, $business] = $this->makeBusinessWithOwner();
        BusinessContext::set($business->id);

        $this->actingAs($owner)
            ->get(route('settings.businesses'))
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->where('can.leave', false)
                    ->where('can.createBusiness', true),
            );
    }

    public function test_create_business_page_flags_an_existing_business_owner(): void
    {
        [$owner, $business] = $this->makeBusinessWithOwner();
        BusinessContext::set($business->id);

        $this->actingAs($owner)
            ->get(route('onboarding.create-business'))
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->component('Onboarding/CreateBusiness')
                    ->where('hasExistingBusiness', true),
            );
    }

    public function test_a_user_with_a_business_can_create_a_second_independent_one(): void
    {
        [$owner, $business] = $this->makeBusinessWithOwner();
        BusinessContext::set($business->id);

        $this->actingAs($owner)
            ->post(route('onboarding.store-business'), ['name' => 'Second Shop'])
            ->assertRedirect(route('onboarding.wizard'));

        $this->assertDatabaseHas('businesses', ['name' => 'Second Shop']);

        // The user now owns two independent businesses.
        $ownerships = Membership::withoutGlobalScope(BusinessScope::class)
            ->where('user_id', $owner->id)
            ->where('role', 'owner')
            ->whereNull('deleted_at')
            ->count();

        $this->assertSame(2, $ownerships);
    }

    protected function tearDown(): void
    {
        BusinessContext::clear();
        parent::tearDown();
    }
}
