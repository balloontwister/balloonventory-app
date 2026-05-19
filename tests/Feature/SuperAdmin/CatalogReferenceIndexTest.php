<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\BalloonSize;
use App\Models\Brand;
use App\Models\Material;
use App\Models\Shape;
use App\Models\Size;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Smoke-test the Reference Data index. Every model registered with image
 * slots in CatalogReferenceController::TABLES must have a matching entry in
 * ImageAttachmentService::CONFIG, or `withImages()` throws. This test exercises
 * the BalloonSize path that previously shipped without that registration.
 */
class CatalogReferenceIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_reference_index_renders_with_balloon_sizes_in_db(): void
    {
        Storage::fake('public');

        $admin = User::factory()->superAdmin()->create(['email_verified_at' => now()]);

        $brand = Brand::create(['name' => 'Qualatex', 'abbreviation' => 'Q', 'sort_order' => 1]);
        $latex = Material::create(['name' => 'Latex', 'sort_order' => 1]);
        $size = Size::create(['name' => '11-inch', 'sort_order' => 30]);
        $round = Shape::create(['name' => 'Round', 'sort_order' => 1]);

        BalloonSize::create([
            'brand_id' => $brand->id,
            'material_id' => $latex->id,
            'size_id' => $size->id,
            'shape_id' => $round->id,
            'name' => '11-inch',
            'sort_order' => 10,
        ]);

        $this->actingAs($admin)
            ->get(route('super-admin.catalog.reference'))
            ->assertOk();
    }
}
