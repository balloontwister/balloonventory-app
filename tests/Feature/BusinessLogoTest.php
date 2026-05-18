<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Membership;
use App\Models\User;
use App\Support\BusinessContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BusinessLogoTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $staff;

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Storage::fake('public');

        $this->owner = User::factory()->create(['email_verified_at' => now()]);
        $this->business = Business::factory()->create();

        Membership::create([
            'user_id' => $this->owner->id,
            'business_id' => $this->business->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        $this->staff = User::factory()->create(['email_verified_at' => now()]);
        Membership::create([
            'user_id' => $this->staff->id,
            'business_id' => $this->business->id,
            'role' => 'staff',
            'joined_at' => now(),
        ]);

        BusinessContext::set($this->business->id);
    }

    protected function tearDown(): void
    {
        BusinessContext::clear();
        parent::tearDown();
    }

    public function test_owner_can_upload_a_business_logo(): void
    {
        $file = UploadedFile::fake()->image('logo.png', 200, 200);

        $response = $this->actingAs($this->owner)
            ->post(route('settings.businesses.logo.update'), [
                'logo' => $file,
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->business->refresh();
        $this->assertNotNull($this->business->logo_path);
        Storage::disk('public')->assertExists($this->business->logo_path);
    }

    public function test_owner_can_clear_a_business_logo(): void
    {
        $file = UploadedFile::fake()->image('logo.png', 200, 200);
        $this->actingAs($this->owner)
            ->post(route('settings.businesses.logo.update'), ['logo' => $file]);

        $this->business->refresh();
        $this->assertNotNull($this->business->logo_path);

        $response = $this->actingAs($this->owner)
            ->post(route('settings.businesses.logo.update'), [
                'logo_clear' => '1',
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertNull($this->business->fresh()->logo_path);
    }

    public function test_non_owner_cannot_upload_a_business_logo(): void
    {
        $file = UploadedFile::fake()->image('logo.png', 200, 200);

        $response = $this->actingAs($this->staff)
            ->post(route('settings.businesses.logo.update'), [
                'logo' => $file,
            ]);

        $response->assertStatus(403);
        $this->assertNull($this->business->fresh()->logo_path);
    }

    public function test_guest_cannot_upload_a_business_logo(): void
    {
        $file = UploadedFile::fake()->image('logo.png', 200, 200);

        $response = $this->post(route('settings.businesses.logo.update'), [
            'logo' => $file,
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_logo_upload_validates_image_type(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->owner)
            ->post(route('settings.businesses.logo.update'), [
                'logo' => $file,
            ]);

        $response->assertSessionHasErrors('logo');
        $this->assertNull($this->business->fresh()->logo_path);
    }

    public function test_logo_upload_validates_max_file_size(): void
    {
        $file = UploadedFile::fake()->image('logo.png')->size(6000);

        $response = $this->actingAs($this->owner)
            ->post(route('settings.businesses.logo.update'), [
                'logo' => $file,
            ]);

        $response->assertSessionHasErrors('logo');
        $this->assertNull($this->business->fresh()->logo_path);
    }

    public function test_uploading_new_logo_replaces_existing_file(): void
    {
        $first = UploadedFile::fake()->image('first.png', 200, 200);
        $this->actingAs($this->owner)
            ->post(route('settings.businesses.logo.update'), ['logo' => $first]);

        $this->business->refresh();
        $firstPath = $this->business->logo_path;

        $second = UploadedFile::fake()->image('second.png', 200, 200);
        $this->actingAs($this->owner)
            ->post(route('settings.businesses.logo.update'), ['logo' => $second]);

        $this->business->refresh();
        $this->assertNotSame($firstPath, $this->business->logo_path);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($this->business->logo_path);
    }

    public function test_upload_sets_success_flash_message(): void
    {
        $file = UploadedFile::fake()->image('logo.png', 200, 200);

        $response = $this->actingAs($this->owner)
            ->post(route('settings.businesses.logo.update'), ['logo' => $file]);

        $response->assertSessionHas('success');
    }
}
