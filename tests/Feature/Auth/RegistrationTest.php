<?php

namespace Tests\Feature\Auth;

use App\Mail\EmailVerificationCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        Mail::fake();

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('verification.code', absolute: false));

        // Code-based verification: the user receives a 6-digit code email,
        // not Laravel's default link-based verification.
        Mail::assertSent(EmailVerificationCode::class);
    }

    public function test_mixed_case_email_is_accepted_and_stored_lowercase(): void
    {
        Mail::fake();

        $response = $this->post('/register', [
            'name' => 'Mixed Case',
            'email' => '  Foo.Bar@Example.COM ',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'foo.bar@example.com']);
    }

    public function test_email_uniqueness_is_case_insensitive(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->post('/register', [
            'name' => 'Dup',
            'email' => 'TAKEN@Example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
