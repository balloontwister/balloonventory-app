<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\BalloonSize;
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

        BalloonSize::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.catalog.reference'))
            ->assertOk();
    }
}
