<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Brand;
use App\Models\Color;
use App\Models\ColorFamily;
use App\Models\Sku;
use App\Models\User;
use App\Services\Catalog\CatalogImageService;
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

        $this->superAdmin = User::factory()->create([
            'is_super_admin' => true,
            'email_verified_at' => now(),
        ]);
    }

    public function test_service_resizes_oversized_images_down_to_max_width(): void
    {
        $brand = Brand::create(['name' => 'Q', 'abbreviation' => 'Q', 'sort_order' => 1]);
        $service = app(CatalogImageService::class);

        $service->set($brand, 'logo', UploadedFile::fake()->image('big.jpg', 3000, 1500));

        $brand->refresh();
        $this->assertNotNull($brand->logo_path);
        Storage::disk('public')->assertExists($brand->logo_path);

        $stored = Storage::disk('public')->get($brand->logo_path);
        $info = getimagesizefromstring($stored);
        $this->assertSame(CatalogImageService::MAX_WIDTH, $info[0], 'Width should be downscaled to MAX_WIDTH');
        $this->assertSame(600, $info[1], 'Height should preserve aspect ratio (3000:1500 → 1200:600)');
    }

    public function test_service_leaves_already_small_images_untouched_in_dimensions(): void
    {
        $brand = Brand::create(['name' => 'Q', 'abbreviation' => 'Q', 'sort_order' => 1]);
        $service = app(CatalogImageService::class);

        $service->set($brand, 'logo', UploadedFile::fake()->image('small.jpg', 400, 200));

        $brand->refresh();
        $stored = Storage::disk('public')->get($brand->logo_path);
        $info = getimagesizefromstring($stored);
        $this->assertSame(400, $info[0]);
        $this->assertSame(200, $info[1]);
    }

    public function test_service_deletes_previous_file_when_replacing(): void
    {
        $brand = Brand::create(['name' => 'Q', 'abbreviation' => 'Q', 'sort_order' => 1]);
        $service = app(CatalogImageService::class);

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
        $brand = Brand::create(['name' => 'Q', 'abbreviation' => 'Q', 'sort_order' => 1]);
        $service = app(CatalogImageService::class);

        $service->set($brand, 'logo', UploadedFile::fake()->image('a.jpg', 500, 500));
        $path = $brand->fresh()->logo_path;
        $this->assertNotNull($path);

        $service->clear($brand, 'logo');

        $this->assertNull($brand->fresh()->logo_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_service_urls_returns_slot_map_with_nulls(): void
    {
        $family = ColorFamily::create(['name' => 'Reds', 'sort_order' => 1]);
        $color = Color::create([
            'name' => 'Crimson',
            'color_family_id' => $family->id,
            'sort_order' => 1,
        ]);
        $service = app(CatalogImageService::class);

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

    public function test_color_store_uploads_single_and_cluster_through_service(): void
    {
        $family = ColorFamily::create(['name' => 'Reds', 'sort_order' => 1]);

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
        $family = ColorFamily::create(['name' => 'Reds', 'sort_order' => 1]);
        $color = Color::create([
            'name' => 'Crimson',
            'color_family_id' => $family->id,
            'sort_order' => 1,
        ]);

        // Seed an existing image via the service.
        app(CatalogImageService::class)->set($color, 'single', UploadedFile::fake()->image('s.jpg', 500, 500));
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

        $response->assertRedirect(route('super-admin.catalog.colors'));
        $this->assertNull($color->fresh()->single_image_file_path);
        Storage::disk('public')->assertMissing($originalPath);
    }

    public function test_sku_store_uploads_both_images_through_service(): void
    {
        $brand = Brand::create(['name' => 'Q', 'abbreviation' => 'Q', 'sort_order' => 1]);

        $response = $this->actingAs($this->superAdmin)
            ->post(route('super-admin.catalog.skus.store'), [
                'name' => 'Test SKU',
                'brand_id' => $brand->id,
                'single_image' => UploadedFile::fake()->image('s.jpg', 800, 800),
                'cluster_image' => UploadedFile::fake()->image('c.jpg', 800, 800),
            ]);

        $response->assertRedirect(route('super-admin.catalog.skus'));
        $sku = Sku::where('name', 'Test SKU')->firstOrFail();
        $this->assertStringStartsWith('sku-images/', $sku->single_image_file_path);
        $this->assertStringStartsWith('sku-images/', $sku->cluster_image_file_path);
        Storage::disk('public')->assertExists($sku->single_image_file_path);
        Storage::disk('public')->assertExists($sku->cluster_image_file_path);
    }

    public function test_brand_update_clear_flag_removes_existing_logo(): void
    {
        $brand = Brand::create(['name' => 'Q', 'abbreviation' => 'Q', 'sort_order' => 1]);
        app(CatalogImageService::class)->set($brand, 'logo', UploadedFile::fake()->image('l.jpg', 500, 500));
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
