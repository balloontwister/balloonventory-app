<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserAvatarTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->user = User::factory()->create(['email_verified_at' => now()]);
    }

    public function test_authenticated_user_can_upload_an_avatar(): void
    {
        $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);

        $response = $this->actingAs($this->user)
            ->post(route('profile.avatar.update'), [
                'avatar' => $file,
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->user->refresh();
        $this->assertNotNull($this->user->avatar_path);
        Storage::disk('public')->assertExists($this->user->avatar_path);
    }

    public function test_authenticated_user_can_clear_their_avatar(): void
    {
        $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);
        $this->actingAs($this->user)
            ->post(route('profile.avatar.update'), ['avatar' => $file]);

        $this->user->refresh();
        $this->assertNotNull($this->user->avatar_path);

        $response = $this->actingAs($this->user)
            ->post(route('profile.avatar.update'), [
                'avatar_clear' => '1',
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertNull($this->user->fresh()->avatar_path);
    }

    public function test_guest_cannot_upload_an_avatar(): void
    {
        $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);

        $response = $this->post(route('profile.avatar.update'), [
            'avatar' => $file,
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_avatar_upload_validates_image_type(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->user)
            ->post(route('profile.avatar.update'), [
                'avatar' => $file,
            ]);

        $response->assertSessionHasErrors('avatar');
        $this->assertNull($this->user->fresh()->avatar_path);
    }

    public function test_avatar_upload_validates_max_file_size(): void
    {
        $file = UploadedFile::fake()->image('avatar.jpg')->size(6000);

        $response = $this->actingAs($this->user)
            ->post(route('profile.avatar.update'), [
                'avatar' => $file,
            ]);

        $response->assertSessionHasErrors('avatar');
        $this->assertNull($this->user->fresh()->avatar_path);
    }

    public function test_uploading_new_avatar_replaces_existing_file(): void
    {
        $first = UploadedFile::fake()->image('first.jpg', 200, 200);
        $this->actingAs($this->user)
            ->post(route('profile.avatar.update'), ['avatar' => $first]);

        $this->user->refresh();
        $firstPath = $this->user->avatar_path;

        $second = UploadedFile::fake()->image('second.jpg', 200, 200);
        $this->actingAs($this->user)
            ->post(route('profile.avatar.update'), ['avatar' => $second]);

        $this->user->refresh();
        $this->assertNotSame($firstPath, $this->user->avatar_path);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($this->user->avatar_path);
    }

    public function test_another_user_cannot_modify_someone_elses_avatar(): void
    {
        $otherUser = User::factory()->create();
        $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);

        // Each user's avatar endpoint operates on the authenticated user only,
        // so acting as $otherUser will only update $otherUser's avatar.
        $this->actingAs($otherUser)
            ->post(route('profile.avatar.update'), ['avatar' => $file]);

        // The original user's avatar is untouched.
        $this->assertNull($this->user->fresh()->avatar_path);
    }

    public function test_upload_sets_success_flash_message(): void
    {
        $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);

        $response = $this->actingAs($this->user)
            ->post(route('profile.avatar.update'), ['avatar' => $file]);

        $response->assertSessionHas('success');
    }
}
