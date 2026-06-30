<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TermsConsentTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_requires_accepting_terms(): void
    {
        $this->post('/register', [
            'name' => 'No Consent',
            'email' => 'noconsent@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            // terms omitted
        ])->assertSessionHasErrors('terms');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'noconsent@example.com']);
    }

    public function test_registration_records_terms_acceptance(): void
    {
        Mail::fake();

        $this->post('/register', [
            'name' => 'Consenter',
            'email' => 'consent@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => true,
        ])->assertSessionHasNoErrors();

        $user = User::where('email', 'consent@example.com')->firstOrFail();
        $this->assertNotNull($user->terms_accepted_at);
        $this->assertSame(config('legal.terms_version'), $user->terms_version);
    }

    public function test_unaccepted_user_is_sent_to_the_interstitial(): void
    {
        $user = User::factory()->termsNotAccepted()->create();

        $this->actingAs($user)->get('/profile')->assertRedirect(route('terms.show'));
    }

    public function test_interstitial_and_policies_are_reachable_when_unaccepted(): void
    {
        $user = User::factory()->termsNotAccepted()->create();

        // No redirect loop: the interstitial itself and the policy pages are open.
        $this->actingAs($user)->get(route('terms.show'))->assertOk();
        $this->actingAs($user)->get('/terms')->assertOk();
        $this->actingAs($user)->get('/privacy')->assertOk();
    }

    public function test_accepted_user_passes_through(): void
    {
        // Factory default = accepted the current version.
        $user = User::factory()->create();

        $this->actingAs($user)->get('/profile')->assertOk();
    }

    public function test_accepting_terms_records_and_unblocks(): void
    {
        $user = User::factory()->termsNotAccepted()->create();

        $this->actingAs($user)
            ->post(route('terms.accept'), ['terms' => true])
            ->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertNotNull($user->terms_accepted_at);
        $this->assertSame(config('legal.terms_version'), $user->terms_version);

        // No longer bounced to the interstitial.
        $this->actingAs($user)->get('/profile')->assertOk();
    }

    public function test_accept_requires_the_checkbox(): void
    {
        $user = User::factory()->termsNotAccepted()->create();

        $this->actingAs($user)
            ->post(route('terms.accept'), ['terms' => false])
            ->assertSessionHasErrors('terms');

        $this->assertNull($user->fresh()->terms_accepted_at);
    }

    public function test_stale_terms_version_triggers_reacceptance(): void
    {
        $user = User::factory()->create([
            'terms_accepted_at' => now(),
            'terms_version' => 'OLD-0000-00-00',
        ]);

        $this->actingAs($user)->get('/profile')->assertRedirect(route('terms.show'));
    }
}
