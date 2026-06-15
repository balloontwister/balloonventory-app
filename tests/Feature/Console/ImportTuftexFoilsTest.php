<?php

namespace Tests\Feature\Console;

use App\Models\Brand;
use App\Models\Material;
use App\Models\Sku;
use Database\Seeders\BrandSeeder;
use Database\Seeders\FoilCatalogSeeder;
use Database\Seeders\MaterialSeeder;
use Database\Seeders\SizeSeeder;
use Database\Seeders\ThemeSeeder;
use Database\Seeders\TufTexFoilBalloonSizeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportTuftexFoilsTest extends TestCase
{
    use RefreshDatabase;

    private string $jsonPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(BrandSeeder::class);
        $this->seed(MaterialSeeder::class);
        $this->seed(SizeSeeder::class);
        $this->seed(ThemeSeeder::class);
        $this->seed(FoilCatalogSeeder::class);
        $this->seed(TufTexFoilBalloonSizeSeeder::class);

        $this->jsonPath = storage_path('app/test_tuftex_foils_normalized.json');
        file_put_contents($this->jsonPath, json_encode($this->fixtureRows(), JSON_PRETTY_PRINT));
    }

    protected function tearDown(): void
    {
        if (file_exists($this->jsonPath)) {
            unlink($this->jsonPath);
        }

        parent::tearDown();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fixtureRows(): array
    {
        return [
            // Square panel, themed, with UPC.
            ['name' => 'Squared Test', 'design' => 'Silver/Gold', 'theme' => 'Everyday', 'size_label' => '24-inch', 'shape' => 'Square', 'balloon_size_name' => '24-inch Foil Square', 'count_per_bag' => 10, 'upc' => '719784783138', 'mfg_no' => '78313', 'is_printed' => true],
            // Star, reuses a base theme.
            ['name' => 'Starbright Test', 'design' => 'Gold star', 'theme' => 'Stars', 'size_label' => '24-inch', 'shape' => 'Star', 'balloon_size_name' => '24-inch Foil Star', 'count_per_bag' => 5, 'upc' => '719784783077', 'mfg_no' => '78307', 'is_printed' => true],
            // Die-cut -> shared "Shaped".
            ['name' => 'Cowgirly Test', 'design' => 'Boot', 'theme' => 'Western', 'size_label' => '26-inch', 'shape' => 'Shaped', 'balloon_size_name' => '26-inch Foil Shaped', 'count_per_bag' => 5, 'upc' => '719784782254', 'mfg_no' => '78225', 'is_printed' => true],
            // Round, no UPC / no item# (e.g. unreleased design).
            ['name' => 'Lovely You Test', 'design' => 'Lovely You', 'theme' => 'Valentine\'s Day', 'size_label' => '18-inch', 'shape' => 'Round', 'balloon_size_name' => '18-inch Foil Round', 'count_per_bag' => 5, 'upc' => null, 'mfg_no' => null, 'is_printed' => true],
        ];
    }

    private function tuftexFoilSkus()
    {
        $brand = Brand::where('name', 'TufTex')->firstOrFail();
        $foil = Material::where('name', 'Foil')->firstOrFail();

        return Sku::where('brand_id', $brand->id)->where('material_id', $foil->id);
    }

    public function test_imports_foil_skus_as_printed_colorless_and_themed(): void
    {
        $this->artisan('catalog:import-tuftex-foils', ['--path' => $this->jsonPath, '--execute' => true])
            ->assertSuccessful();

        $this->assertSame(4, $this->tuftexFoilSkus()->count());
        $this->assertSame(4, $this->tuftexFoilSkus()->where('is_printed', true)->count());
        $this->assertSame(4, $this->tuftexFoilSkus()->whereNull('color_id')->count());
        $this->assertSame(4, $this->tuftexFoilSkus()->has('themes')->count());

        $square = Sku::where('name', 'Squared Test')->with('balloonSize', 'themes')->firstOrFail();
        $this->assertSame('24-inch Foil Square', $square->balloonSize->name);
        $this->assertSame('Everyday', $square->themes->first()->name);
        $this->assertSame('719784783138', $square->upc);
        $this->assertSame(10, (int) $square->default_count_per_bag);
    }

    public function test_die_cut_design_maps_to_the_shared_shaped_size(): void
    {
        $this->artisan('catalog:import-tuftex-foils', ['--path' => $this->jsonPath, '--execute' => true]);

        $boot = Sku::where('name', 'Cowgirly Test')->with('balloonSize.shape')->firstOrFail();

        $this->assertSame('26-inch Foil Shaped', $boot->balloonSize->name);
        $this->assertSame('Shaped', $boot->balloonSize->shape->name);
    }

    public function test_row_without_upc_imports_with_null_upc(): void
    {
        $this->artisan('catalog:import-tuftex-foils', ['--path' => $this->jsonPath, '--execute' => true]);

        $sku = Sku::where('name', 'Lovely You Test')->firstOrFail();

        $this->assertNull($sku->upc);
        $this->assertNull($sku->mfg_no);
        $this->assertTrue((bool) $sku->is_printed);
    }

    public function test_is_idempotent_on_a_second_run(): void
    {
        $this->artisan('catalog:import-tuftex-foils', ['--path' => $this->jsonPath, '--execute' => true]);
        $this->artisan('catalog:import-tuftex-foils', ['--path' => $this->jsonPath, '--execute' => true]);

        $this->assertSame(4, $this->tuftexFoilSkus()->count());
    }

    public function test_unresolved_balloon_size_is_warned_and_skipped(): void
    {
        $rows = $this->fixtureRows();
        $rows[] = ['name' => 'Bad Combo', 'design' => 'x', 'theme' => 'Everyday', 'size_label' => '99-inch', 'shape' => 'Round', 'balloon_size_name' => '99-inch Foil Round', 'count_per_bag' => 5, 'upc' => null, 'mfg_no' => null, 'is_printed' => true];
        file_put_contents($this->jsonPath, json_encode($rows, JSON_PRETTY_PRINT));

        $this->artisan('catalog:import-tuftex-foils', ['--path' => $this->jsonPath, '--execute' => true])
            ->expectsConfirmation('There are 1 warnings. Continue anyway?', 'yes')
            ->assertSuccessful();

        $this->assertSame(4, $this->tuftexFoilSkus()->count());
        $this->assertDatabaseMissing('skus', ['name' => 'Bad Combo']);
    }
}
