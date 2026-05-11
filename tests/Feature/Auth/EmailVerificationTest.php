<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_verification_code_screen_can_be_rendered(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get('/verify-code');

        $response->assertStatus(200);
    }

    public function test_email_can_be_verified_with_correct_code(): void
    {
        $user = User::factory()->unverified()->create([
            'email_verification_code' => '123456',
            'email_verification_code_expires_at' => Carbon::now()->addMinutes(15),
        ]);

        Event::fake();

        $response = $this->actingAs($user)->post('/verify-code', ['code' => '123456']);

        Event::assertDispatched(Verified::class);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_email_is_not_verified_with_wrong_code(): void
    {
        $user = User::factory()->unverified()->create([
            'email_verification_code' => '123456',
            'email_verification_code_expires_at' => Carbon::now()->addMinutes(15),
        ]);

        $response = $this->actingAs($user)
            ->from('/verify-code')
            ->post('/verify-code', ['code' => '999999']);

        $response->assertSessionHasErrors('code');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_email_is_not_verified_with_expired_code(): void
    {
        $user = User::factory()->unverified()->create([
            'email_verification_code' => '123456',
            'email_verification_code_expires_at' => Carbon::now()->subMinute(),
        ]);

        $response = $this->actingAs($user)
            ->from('/verify-code')
            ->post('/verify-code', ['code' => '123456']);

        $response->assertSessionHasErrors('code');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }
}
