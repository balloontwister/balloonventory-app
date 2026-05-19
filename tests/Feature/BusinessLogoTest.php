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

    public function test_unauthenticated_user_cannot_upload_a_business_logo(): void
    {
        $file = UploadedFile::fake()->image('logo.png', 200, 200);

        $response = $this->post(route('settings.businesses.logo.update'), [
            'logo' => $file,
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_manager_cannot_upload_a_business_logo(): void
    {
        $manager = User::factory()->create(['email_verified_at' => now()]);
        Membership::create([
            'user_id' => $manager->id,
            'business_id' => $this->business->id,
            'role' => 'manager',
            'joined_at' => now(),
        ]);

        $file = UploadedFile::fake()->image('logo.png', 200, 200);

        $response = $this->actingAs($manager)
            ->post(route('settings.businesses.logo.update'), ['logo' => $file]);

        $response->assertStatus(403);
        $this->assertNull($this->business->fresh()->logo_path);
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

    public function test_svg_upload_strips_active_content(): void
    {
        $svg = <<<'SVG'
            <?xml version="1.0" encoding="UTF-8"?>
            <svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">
                <script type="text/javascript">alert('xss')</script>
                <rect width="100" height="100" fill="red" onload="alert('xss')" onclick="alert(2)"/>
                <a xlink:href="javascript:alert(3)" xmlns:xlink="http://www.w3.org/1999/xlink">
                    <text>click me</text>
                </a>
            </svg>
            SVG;

        $file = UploadedFile::fake()->createWithContent('logo.svg', $svg);

        $response = $this->actingAs($this->owner)
            ->post(route('settings.businesses.logo.update'), ['logo' => $file]);

        $response->assertSessionHasNoErrors();
        $this->business->refresh();
        $this->assertNotNull($this->business->logo_path);

        $stored = Storage::disk('public')->get($this->business->logo_path);
        $this->assertStringNotContainsStringIgnoringCase('<script', $stored);
        $this->assertStringNotContainsStringIgnoringCase('onload', $stored);
        $this->assertStringNotContainsStringIgnoringCase('onclick', $stored);
        $this->assertStringNotContainsStringIgnoringCase('javascript:', $stored);
        // Legitimate vector content is preserved.
        $this->assertStringContainsString('<rect', $stored);
    }

    public function test_svg_upload_strips_javascript_href(): void
    {
        $svg = <<<'SVG'
            <?xml version="1.0" encoding="UTF-8"?>
            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="100" height="100">
                <a xlink:href="javascript:alert(1)" href="javascript:alert(2)">
                    <rect width="100" height="100" fill="blue"/>
                </a>
            </svg>
            SVG;

        $file = UploadedFile::fake()->createWithContent('logo.svg', $svg);

        $response = $this->actingAs($this->owner)
            ->post(route('settings.businesses.logo.update'), ['logo' => $file]);

        $response->assertSessionHasNoErrors();
        $this->business->refresh();

        $stored = Storage::disk('public')->get($this->business->logo_path);
        $this->assertStringNotContainsStringIgnoringCase('javascript:', $stored);
        $this->assertStringContainsString('<rect', $stored);
    }

    public function test_svg_upload_strips_external_url_refs(): void
    {
        $svg = <<<'SVG'
            <?xml version="1.0" encoding="UTF-8"?>
            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="100" height="100">
                <image xlink:href="https://tracker.example.com/pixel.png" width="1" height="1"/>
                <image href="http://tracker.example.com/pixel2.png" width="1" height="1"/>
                <use xlink:href="//tracker.example.com/payload.svg#x"/>
                <use xlink:href="#localShape"/>
                <rect id="localShape" width="100" height="100" fill="green"/>
            </svg>
            SVG;

        $file = UploadedFile::fake()->createWithContent('logo.svg', $svg);

        $response = $this->actingAs($this->owner)
            ->post(route('settings.businesses.logo.update'), ['logo' => $file]);

        $response->assertSessionHasNoErrors();
        $this->business->refresh();

        $stored = Storage::disk('public')->get($this->business->logo_path);
        $this->assertStringNotContainsString('tracker.example.com', $stored);
        // Local fragment refs and legitimate vector content survive.
        $this->assertStringContainsString('#localShape', $stored);
        $this->assertStringContainsString('<rect', $stored);
    }

    public function test_svg_upload_rejects_unparseable_payload(): void
    {
        // Looks like an SVG to mime sniffing but loadXML() will refuse to parse it.
        $broken = '<svg xmlns="http://www.w3.org/2000/svg"><<<not xml at all';

        $file = UploadedFile::fake()->createWithContent('logo.svg', $broken);

        $response = $this->actingAs($this->owner)
            ->post(route('settings.businesses.logo.update'), ['logo' => $file]);

        // Either the image rule rejects it, or the sanitizer rejects it via
        // ValidationException — both produce an error keyed on "logo".
        $response->assertSessionHasErrors('logo');
        $this->assertNull($this->business->fresh()->logo_path);
    }

    public function test_uploaded_logo_is_resized_to_business_max_width(): void
    {
        // Source is well above the 400px Business max_width in ImageAttachmentService.
        $file = UploadedFile::fake()->image('huge.png', 2000, 2000);

        $this->actingAs($this->owner)
            ->post(route('settings.businesses.logo.update'), ['logo' => $file]);

        $this->business->refresh();
        $stored = Storage::disk('public')->get($this->business->logo_path);
        $info = getimagesizefromstring($stored);

        $this->assertNotFalse($info);
        $this->assertSame(400, $info[0]);
    }

    public function test_logo_upload_rejects_pixel_bomb_dimensions(): void
    {
        // 10000×10000 exceeds MAX_SOURCE_DIMENSION (8000).
        $file = UploadedFile::fake()->image('bomb.png', 10000, 10000);

        $response = $this->actingAs($this->owner)
            ->post(route('settings.businesses.logo.update'), ['logo' => $file]);

        $response->assertSessionHasErrors('logo');
        $this->assertNull($this->business->fresh()->logo_path);
    }

    public function test_logo_file_takes_precedence_over_clear_flag(): void
    {
        $first = UploadedFile::fake()->image('first.png', 200, 200);
        $this->actingAs($this->owner)
            ->post(route('settings.businesses.logo.update'), ['logo' => $first]);
        $this->business->refresh();
        $firstPath = $this->business->logo_path;

        $second = UploadedFile::fake()->image('second.png', 200, 200);
        $this->actingAs($this->owner)
            ->post(route('settings.businesses.logo.update'), [
                'logo' => $second,
                'logo_clear' => '1',
            ]);

        $this->business->refresh();
        $this->assertNotNull($this->business->logo_path);
        $this->assertNotSame($firstPath, $this->business->logo_path);
    }

    public function test_empty_logo_submit_is_a_noop(): void
    {
        $existing = UploadedFile::fake()->image('existing.png', 200, 200);
        $this->actingAs($this->owner)
            ->post(route('settings.businesses.logo.update'), ['logo' => $existing]);
        $this->business->refresh();
        $existingPath = $this->business->logo_path;

        $response = $this->actingAs($this->owner)
            ->post(route('settings.businesses.logo.update'), []);

        $response->assertSessionHasNoErrors();
        $this->assertSame($existingPath, $this->business->fresh()->logo_path);
        Storage::disk('public')->assertExists($existingPath);
    }

    public function test_default_business_logo_url_uses_locale_specific_image(): void
    {
        // No logo uploaded — middleware falls back to the locale default.
        // Test against /dashboard — it doesn't override the shared business
        // prop, so the middleware's locale-aware fallback comes through.
        $this->owner->update(['locale' => 'en']);

        $this->actingAs($this->owner)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where(
                'business.logoUrl',
                fn ($url) => is_string($url) && str_contains($url, 'balloon-company-logo-light-default')
            ));

        $this->owner->update(['locale' => 'es']);

        $this->actingAs($this->owner)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where(
                'business.logoUrl',
                fn ($url) => is_string($url) && str_contains($url, 'balloon-company-es-logo-light-default')
            ));
    }
}
