<?php

namespace Tests\Feature;

use App\Models\Bin;
use App\Models\Business;
use App\Models\Location;
use App\Scopes\BusinessScope;
use App\Services\BinResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BinResolverTest extends TestCase
{
    use RefreshDatabase;

    private function resolver(): BinResolver
    {
        return app(BinResolver::class);
    }

    public function test_resolve_default_returns_the_existing_default_bin(): void
    {
        $business = Business::factory()->create();
        $location = Location::factory()->default()->create(['business_id' => $business->id]);
        $default = Bin::factory()->default()->create([
            'business_id' => $business->id,
            'location_id' => $location->id,
        ]);
        Bin::factory()->create([
            'business_id' => $business->id,
            'location_id' => $location->id,
        ]);

        $resolved = $this->resolver()->resolveDefault($business);

        $this->assertSame($default->id, $resolved->id);
        $this->assertTrue($resolved->is_default);
    }

    public function test_resolve_default_creates_location_and_bin_when_business_has_none(): void
    {
        $business = Business::factory()->create();

        $this->assertNull($business->defaultBin());

        $resolved = $this->resolver()->resolveDefault($business);

        $this->assertTrue($resolved->is_default);
        $this->assertSame('Default', $resolved->name);

        $location = Location::withoutGlobalScope(BusinessScope::class)->find($resolved->location_id);
        $this->assertNotNull($location);
        $this->assertTrue($location->is_default);
        $this->assertSame($business->id, $resolved->business_id);
    }

    public function test_resolve_default_reuses_existing_default_location(): void
    {
        $business = Business::factory()->create();
        $location = Location::factory()->default()->create(['business_id' => $business->id]);

        $resolved = $this->resolver()->resolveDefault($business);

        $this->assertSame($location->id, $resolved->location_id);
        $this->assertSame(
            1,
            Location::withoutGlobalScope(BusinessScope::class)
                ->where('business_id', $business->id)
                ->count(),
            'A second Default location should not have been created.'
        );
    }
}
