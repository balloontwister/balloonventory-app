<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Color;
use App\Models\User;
use Database\Seeders\BrandSeeder;
use Database\Seeders\ColorFamilySeeder;
use Database\Seeders\MaterialSeeder;
use Database\Seeders\TextureFamilySeeder;
use Database\Seeders\TextureSeeder;
use Database\Seeders\TufTexColorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogColorShowTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->superAdmin()->create([
            'email_verified_at' => now(),
        ]);

        $this->seed(BrandSeeder::class);
        $this->seed(MaterialSeeder::class);
        $this->seed(TextureFamilySeeder::class);
        $this->seed(TextureSeeder::class);
        $this->seed(ColorFamilySeeder::class);
        $this->seed(TufTexColorSeeder::class);
    }

    public function test_show_page_renders_for_a_color(): void
    {
        $color = Color::where('name', 'Turquoise')->firstOrFail();

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.catalog.colors.show', $color))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('SuperAdmin/Catalog/ColorShow')
                    ->has('color')
                    ->where('color.name', 'Turquoise')
                    ->where('color.color_hex', '#009EC4')
                    ->where('color.pms_value', 'PMS 312 C'),
            );
    }

    public function test_show_page_includes_related_data(): void
    {
        $color = Color::where('name', 'Coral')->firstOrFail();

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.catalog.colors.show', $color))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->where('color.brand.name', 'TufTex')
                    ->where('color.texture.name', 'Designer')
                    ->where('color.material.name', 'Latex')
                    ->where('color.color_family.name', 'Oranges'),
            );
    }

    public function test_show_page_is_inaccessible_to_guests(): void
    {
        $color = Color::where('name', 'Turquoise')->firstOrFail();

        $this->get(route('super-admin.catalog.colors.show', $color))
            ->assertRedirect(route('login'));
    }

    public function test_edit_page_renders_with_form_data(): void
    {
        $color = Color::where('name', 'Turquoise')->firstOrFail();

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.catalog.colors.edit', $color))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('SuperAdmin/Catalog/ColorEdit')
                    ->has('color')
                    ->has('colorFamilies')
                    ->has('brands')
                    ->where('color.name', 'Turquoise'),
            );
    }

    public function test_update_redirects_to_show_page(): void
    {
        $color = Color::where('name', 'Turquoise')->firstOrFail();

        $this->actingAs($this->superAdmin)
            ->patch(route('super-admin.catalog.colors.update', $color), [
                'name' => 'Turquoise',
                'color_family_id' => $color->color_family_id,
                'brand_id' => $color->brand_id,
                'texture_id' => $color->texture_id,
                'color_hex' => '#009EC4',
                'sort_order' => $color->sort_order,
            ])
            ->assertRedirect(route('super-admin.catalog.colors.show', $color));
    }

    public function test_colors_index_includes_show_route_data(): void
    {
        $color = Color::where('name', 'Turquoise')->firstOrFail();

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.catalog.colors'))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('SuperAdmin/Catalog/Colors')
                    ->has('colorFamilies'),
            );

        // Verify the show route resolves for this color.
        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.catalog.colors.show', $color))
            ->assertOk();
    }
}
