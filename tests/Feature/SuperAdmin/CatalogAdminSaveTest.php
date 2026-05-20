<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Brand;
use App\Models\ColorFamily;
use App\Models\Texture;
use App\Models\TextureFamily;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards against the silent-failure class of bug where the catalog admin
 * controllers validate one column name but the model's $fillable lists a
 * different one (post-rename / post-FK-conversion). Laravel's default
 * mass-assign silently drops the non-fillable field, so the form appears to
 * save but the user-entered value is discarded.
 */
class CatalogAdminSaveTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->superAdmin()->create([
            'email_verified_at' => now(),
        ]);
    }

    public function test_brand_store_persists_primary_color_hex(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->post(route('super-admin.catalog.brands.store'), [
                'name' => 'Qualatex',
                'abbreviation' => 'QTX',
                'primary_color_hex' => '#ff0000',
                'sort_order' => 1,
            ]);

        $response->assertRedirect(route('super-admin.catalog.brands'));
        $this->assertDatabaseHas('brands', [
            'name' => 'Qualatex',
            'primary_color_hex' => '#ff0000',
        ]);
    }

    public function test_brand_update_persists_primary_color_hex(): void
    {
        $brand = Brand::factory()->create([
            'name' => 'Qualatex',
            'abbreviation' => 'QTX',
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->patch(route('super-admin.catalog.brands.update', $brand->id), [
                'name' => 'Qualatex',
                'abbreviation' => 'QTX',
                'primary_color_hex' => '#abcdef',
                'sort_order' => 1,
            ]);

        $response->assertRedirect(route('super-admin.catalog.brands'));
        $this->assertSame('#abcdef', $brand->fresh()->primary_color_hex);
    }

    public function test_textures_reference_store_persists_texture_family_id(): void
    {
        $family = TextureFamily::factory()->create();

        $response = $this->actingAs($this->superAdmin)
            ->post(route('super-admin.catalog.reference.store', 'textures'), [
                'name' => 'Crystal Standard',
                'texture_family_id' => $family->id,
                'sort_order' => 1,
            ]);

        $response->assertRedirect(route('super-admin.catalog.reference'));
        $texture = Texture::where('name', 'Crystal Standard')->firstOrFail();
        $this->assertSame($family->id, $texture->texture_family_id);
    }

    public function test_textures_reference_update_persists_texture_family_id(): void
    {
        $crystal = TextureFamily::factory()->create();
        $metallic = TextureFamily::factory()->create();

        $texture = Texture::factory()->create([
            'name' => 'Pearl',
            'sort_order' => 1,
            'texture_family_id' => $crystal->id,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->patch(route('super-admin.catalog.reference.update', ['table' => 'textures', 'item' => $texture->id]), [
                'name' => 'Pearl',
                'texture_family_id' => $metallic->id,
                'sort_order' => 1,
            ]);

        $response->assertRedirect(route('super-admin.catalog.reference'));
        $this->assertSame($metallic->id, $texture->fresh()->texture_family_id);
    }

    public function test_textures_reference_store_rejects_missing_texture_family_id(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->post(route('super-admin.catalog.reference.store', 'textures'), [
                'name' => 'Floating Texture',
                'sort_order' => 1,
            ]);

        $response->assertSessionHasErrors('texture_family_id');
        $this->assertDatabaseMissing('textures', ['name' => 'Floating Texture']);
    }

    public function test_color_families_reference_store_persists_fallback_color_hex(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->post(route('super-admin.catalog.reference.store', 'color-families'), [
                'name' => 'Reds',
                'fallback_color_hex' => '#ff0000',
                'sort_order' => 1,
            ]);

        $response->assertRedirect(route('super-admin.catalog.reference'));
        $family = ColorFamily::where('name', 'Reds')->firstOrFail();
        $this->assertSame('#ff0000', $family->fallback_color_hex);
    }

    public function test_color_families_reference_update_persists_fallback_color_hex(): void
    {
        $family = ColorFamily::factory()->create([
            'name' => 'Reds',
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->patch(route('super-admin.catalog.reference.update', ['table' => 'color-families', 'item' => $family->id]), [
                'name' => 'Reds',
                'fallback_color_hex' => '#dd1144',
                'sort_order' => 1,
            ]);

        $response->assertRedirect(route('super-admin.catalog.reference'));
        $this->assertSame('#dd1144', $family->fresh()->fallback_color_hex);
    }

    public function test_reference_index_passes_texture_families_and_eager_loads_family_on_textures(): void
    {
        $family = TextureFamily::factory()->create(['name' => 'Crystal']);
        Texture::factory()->create([
            'texture_family_id' => $family->id,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.catalog.reference'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('textureFamilies', 1, fn ($f) => $f
                ->where('id', $family->id)
                ->where('name', 'Crystal')
                ->etc()
            )
            ->has('textures', 1, fn ($t) => $t
                ->where('texture_family.name', 'Crystal')
                ->etc()
            )
        );
    }
}
