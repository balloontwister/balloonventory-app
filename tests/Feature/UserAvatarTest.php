<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ImageAttachmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
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

    public function test_unauthenticated_user_cannot_upload_an_avatar(): void
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

    public function test_uploaded_avatar_is_resized_to_user_max_width(): void
    {
        // Source is well above the 400px User max_width in ImageAttachmentService.
        $file = UploadedFile::fake()->image('huge.png', 2000, 2000);

        $this->actingAs($this->user)
            ->post(route('profile.avatar.update'), ['avatar' => $file]);

        $this->user->refresh();
        $stored = Storage::disk('public')->get($this->user->avatar_path);
        $info = getimagesizefromstring($stored);

        $this->assertNotFalse($info);
        $this->assertSame(400, $info[0]);
    }

    public function test_avatar_upload_rejects_pixel_bomb_dimensions(): void
    {
        $file = UploadedFile::fake()->image('bomb.png', 10000, 10000);

        $response = $this->actingAs($this->user)
            ->post(route('profile.avatar.update'), ['avatar' => $file]);

        $response->assertSessionHasErrors('avatar');
        $this->assertNull($this->user->fresh()->avatar_path);
    }

    public function test_avatar_file_takes_precedence_over_clear_flag(): void
    {
        $first = UploadedFile::fake()->image('first.jpg', 200, 200);
        $this->actingAs($this->user)
            ->post(route('profile.avatar.update'), ['avatar' => $first]);
        $this->user->refresh();
        $firstPath = $this->user->avatar_path;

        // Both a new file AND avatar_clear=1 — file should win.
        $second = UploadedFile::fake()->image('second.jpg', 200, 200);
        $this->actingAs($this->user)
            ->post(route('profile.avatar.update'), [
                'avatar' => $second,
                'avatar_clear' => '1',
            ]);

        $this->user->refresh();
        $this->assertNotNull($this->user->avatar_path);
        $this->assertNotSame($firstPath, $this->user->avatar_path);
    }

    public function test_empty_avatar_submit_is_a_noop(): void
    {
        $existing = UploadedFile::fake()->image('avatar.jpg', 200, 200);
        $this->actingAs($this->user)
            ->post(route('profile.avatar.update'), ['avatar' => $existing]);
        $this->user->refresh();
        $existingPath = $this->user->avatar_path;

        $response = $this->actingAs($this->user)
            ->post(route('profile.avatar.update'), []);

        $response->assertSessionHasNoErrors();
        $this->assertSame($existingPath, $this->user->fresh()->avatar_path);
        Storage::disk('public')->assertExists($existingPath);
    }

    public function test_rollback_deletes_new_file_when_model_save_fails(): void
    {
        // Inject a saving listener that throws — simulates a DB failure or
        // observer veto landing AFTER the new file is written to storage.
        User::saving(function () {
            throw new \RuntimeException('simulated save failure');
        });

        try {
            $service = app(ImageAttachmentService::class);
            $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);

            try {
                $service->set($this->user, 'avatar', $file);
                $this->fail('Expected RuntimeException');
            } catch (\RuntimeException $e) {
                $this->assertSame('simulated save failure', $e->getMessage());
            }

            // New file was rolled back; user still has no avatar.
            $this->assertEmpty(Storage::disk('public')->files('user-avatars'));
            $this->assertNull($this->user->fresh()->avatar_path);
        } finally {
            Event::forget('eloquent.saving: '.User::class);
        }
    }
}
