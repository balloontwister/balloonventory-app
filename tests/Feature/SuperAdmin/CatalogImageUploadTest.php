<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Brand;
use App\Models\Color;
use App\Models\ColorFamily;
use App\Models\Sku;
use App\Models\User;
use App\Services\ImageAttachmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Exercises the image upload pipeline end-to-end for the catalog admin:
 *  - service resizes images larger than MAX_WIDTH, leaves smaller ones alone
 *  - service deletes previously stored files on replace / clear
 *  - controllers persist uploaded images through the service
 *  - clear flag wipes an existing image without uploading a new one
 */
class CatalogImageUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->superAdmin = User::factory()->superAdmin()->create([
            'email_verified_at' => now(),
        ]);
    }

    public function test_service_resizes_oversized_images_down_to_max_width(): void
    {
        $brand = Brand::factory()->create();
        $service = app(ImageAttachmentService::class);

        $service->set($brand, 'logo', UploadedFile::fake()->image('big.jpg', 3000, 1500));

        $brand->refresh();
        $this->assertNotNull($brand->logo_path);
        Storage::disk('public')->assertExists($brand->logo_path);

        $stored = Storage::disk('public')->get($brand->logo_path);
        $info = getimagesizefromstring($stored);
        $this->assertSame(ImageAttachmentService::MAX_WIDTH, $info[0], 'Width should be downscaled to MAX_WIDTH');
        $this->assertSame(600, $info[1], 'Height should preserve aspect ratio (3000:1500 → 1200:600)');
    }

    public function test_service_leaves_already_small_images_untouched_in_dimensions(): void
    {
        $brand = Brand::factory()->create();
        $service = app(ImageAttachmentService::class);

        $service->set($brand, 'logo', UploadedFile::fake()->image('small.jpg', 400, 200));

        $brand->refresh();
        $stored = Storage::disk('public')->get($brand->logo_path);
        $info = getimagesizefromstring($stored);
        $this->assertSame(400, $info[0]);
        $this->assertSame(200, $info[1]);
    }

    public function test_service_deletes_previous_file_when_replacing(): void
    {
        $brand = Brand::factory()->create();
        $service = app(ImageAttachmentService::class);

        $service->set($brand, 'logo', UploadedFile::fake()->image('a.jpg', 500, 500));
        $brand->refresh();
        $firstPath = $brand->logo_path;

        $service->set($brand, 'logo', UploadedFile::fake()->image('b.jpg', 500, 500));
        $brand->refresh();

        $this->assertNotSame($firstPath, $brand->logo_path);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($brand->logo_path);
    }

    public function test_service_clear_removes_file_and_nulls_column(): void
    {
        $brand = Brand::factory()->create();
        $service = app(ImageAttachmentService::class);

        $service->set($brand, 'logo', UploadedFile::fake()->image('a.jpg', 500, 500));
        $path = $brand->fresh()->logo_path;
        $this->assertNotNull($path);

        $service->clear($brand, 'logo');

        $this->assertNull($brand->fresh()->logo_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_service_urls_returns_slot_map_with_nulls(): void
    {
        $color = Color::factory()->create();
        $service = app(ImageAttachmentService::class);

        // Both slots empty initially.
        $this->assertSame(['single' => null, 'cluster' => null], $service->urls($color));

        // Set just single; cluster remains null.
        $service->set($color, 'single', UploadedFile::fake()->image('s.jpg', 500, 500));
        $urls = $service->urls($color->fresh());
        $this->assertNotNull($urls['single']);
        $this->assertNull($urls['cluster']);
    }

    public function test_brand_store_uploads_logo_through_service(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->post(route('super-admin.catalog.brands.store'), [
                'name' => 'Qualatex',
                'abbreviation' => 'QTX',
                'sort_order' => 1,
                'logo' => UploadedFile::fake()->image('logo.jpg', 800, 800),
            ]);

        $response->assertRedirect(route('super-admin.catalog.brands'));
        $brand = Brand::where('name', 'Qualatex')->firstOrFail();
        $this->assertNotNull($brand->logo_path);
        Storage::disk('public')->assertExists($brand->logo_path);
        $this->assertStringStartsWith('brand-logos/', $brand->logo_path);
    }

    public function test_brand_store_accepts_svg_logo(): void
    {
        // Laravel 11+ excludes SVG from the `image` rule by default; the
        // controller must opt in with `image:allow_svg` for vector vendor
        // logos to validate.
        $svg = '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" fill="#f00"/></svg>';
        $file = UploadedFile::fake()->createWithContent('logo.svg', $svg);

        $response = $this->actingAs($this->superAdmin)
            ->post(route('super-admin.catalog.brands.store'), [
                'name' => 'Anagram',
                'abbreviation' => 'ANG',
                'sort_order' => 1,
                'logo' => $file,
            ]);

        $response->assertSessionDoesntHaveErrors('logo');
        $response->assertRedirect(route('super-admin.catalog.brands'));
        $brand = Brand::where('name', 'Anagram')->firstOrFail();
        $this->assertNotNull($brand->logo_path);
        $this->assertStringEndsWith('.svg', $brand->logo_path);

        // SVGs are sanitized (re-serialized via DOMDocument) rather than
        // byte-copied, so we assert semantic preservation instead of equality.
        $stored = Storage::disk('public')->get($brand->logo_path);
        $this->assertStringContainsString('<svg', $stored);
        $this->assertStringContainsString('<rect', $stored);
        $this->assertStringContainsString('#f00', $stored);
    }

    public function test_color_store_uploads_single_and_cluster_through_service(): void
    {
        $family = ColorFamily::factory()->create();

        $response = $this->actingAs($this->superAdmin)
            ->post(route('super-admin.catalog.colors.store'), [
                'name' => 'Crimson',
                'color_family_id' => $family->id,
                'sort_order' => 1,
                'single_image' => UploadedFile::fake()->image('single.jpg', 500, 500),
                'cluster_image' => UploadedFile::fake()->image('cluster.jpg', 500, 500),
            ]);

        $response->assertRedirect(route('super-admin.catalog.colors'));
        $color = Color::where('name', 'Crimson')->firstOrFail();
        $this->assertNotNull($color->single_image_file_path);
        $this->assertNotNull($color->cluster_image_file_path);
        $this->assertStringStartsWith('color-images/', $color->single_image_file_path);
        Storage::disk('public')->assertExists($color->single_image_file_path);
        Storage::disk('public')->assertExists($color->cluster_image_file_path);
    }

    public function test_color_update_clear_flag_removes_existing_image(): void
    {
        $family = ColorFamily::factory()->create();
        $color = Color::factory()->create([
            'color_family_id' => $family->id,
        ]);

        // Seed an existing image via the service.
        app(ImageAttachmentService::class)->set($color, 'single', UploadedFile::fake()->image('s.jpg', 500, 500));
        $color->refresh();
        $originalPath = $color->single_image_file_path;
        $this->assertNotNull($originalPath);

        $response = $this->actingAs($this->superAdmin)
            ->patch(route('super-admin.catalog.colors.update', $color->id), [
                'name' => 'Crimson',
                'color_family_id' => $family->id,
                'sort_order' => 1,
                'single_image_clear' => true,
            ]);

        $response->assertRedirect(route('super-admin.catalog.colors.show', $color));
        $this->assertNull($color->fresh()->single_image_file_path);
        Storage::disk('public')->assertMissing($originalPath);
    }

    public function test_sku_store_uploads_both_images_through_service(): void
    {
        $brand = Brand::factory()->create();

        $response = $this->actingAs($this->superAdmin)
            ->post(route('super-admin.catalog.skus.store'), [
                'name' => 'Test SKU',
                'brand_id' => $brand->id,
                'single_image' => UploadedFile::fake()->image('s.jpg', 800, 800),
                'cluster_image' => UploadedFile::fake()->image('c.jpg', 800, 800),
            ]);

        $sku = Sku::where('name', 'Test SKU')->firstOrFail();
        $response->assertRedirect(route('super-admin.catalog.skus.show', $sku));
        $this->assertStringStartsWith('sku-images/', $sku->single_image_file_path);
        $this->assertStringStartsWith('sku-images/', $sku->cluster_image_file_path);
        Storage::disk('public')->assertExists($sku->single_image_file_path);
        Storage::disk('public')->assertExists($sku->cluster_image_file_path);
    }

    public function test_brand_update_clear_flag_removes_existing_logo(): void
    {
        $brand = Brand::factory()->create(['name' => 'Q', 'abbreviation' => 'Q', 'sort_order' => 1]);
        app(ImageAttachmentService::class)->set($brand, 'logo', UploadedFile::fake()->image('l.jpg', 500, 500));
        $brand->refresh();
        $originalPath = $brand->logo_path;
        $this->assertNotNull($originalPath);

        $response = $this->actingAs($this->superAdmin)
            ->patch(route('super-admin.catalog.brands.update', $brand->id), [
                'name' => 'Q',
                'abbreviation' => 'Q',
                'sort_order' => 1,
                'logo_clear' => true,
            ]);

        $response->assertRedirect(route('super-admin.catalog.brands'));
        $this->assertNull($brand->fresh()->logo_path);
        Storage::disk('public')->assertMissing($originalPath);
    }
}
