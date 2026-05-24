<?php

namespace Tests\Feature\Console;

use App\Models\Brand;
use App\Models\PackagingType;
use App\Models\Sku;
use Database\Seeders\BrandSeeder;
use Database\Seeders\ColorFamilySeeder;
use Database\Seeders\MaterialSeeder;
use Database\Seeders\PackagingTypeSeeder;
use Database\Seeders\SempertexBalloonSizeSeeder;
use Database\Seeders\SempertexColorSeeder;
use Database\Seeders\SempertexTextureSeeder;
use Database\Seeders\ShapeSeeder;
use Database\Seeders\SizeSeeder;
use Database\Seeders\TextureFamilySeeder;
use Database\Seeders\TextureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportSempertexSkusTest extends TestCase
{
    use RefreshDatabase;

    private string $jsonPath;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Http::fake(['*' => Http::response('fake-jpg-bytes', 200, ['Content-Type' => 'image/jpeg'])]);

        $this->seed(BrandSeeder::class);
        $this->seed(MaterialSeeder::class);
        $this->seed(ShapeSeeder::class);
        $this->seed(SizeSeeder::class);
        $this->seed(PackagingTypeSeeder::class);
        $this->seed(TextureFamilySeeder::class);
        $this->seed(TextureSeeder::class);
        $this->seed(ColorFamilySeeder::class);
        $this->seed(SempertexTextureSeeder::class);
        $this->seed(SempertexBalloonSizeSeeder::class);
        $this->seed(SempertexColorSeeder::class);

        $this->jsonPath = storage_path('app/test_sempertex_normalized.json');
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
            // Same physical balloon in two pack sizes — should link via identical_skus.
            ['warehouse_sku' => '20000001', 'raw_name' => 'R-12 Fashion Red - 12CT',          'shape_label' => 'Round', 'size_resolved' => 'R-12', 'texture_resolved' => 'Fashion (S)', 'color_resolved' => 'Fashion Red',         'packaging' => 'Loose', 'count_per_bag' => 12],
            ['warehouse_sku' => '20000002', 'raw_name' => 'R-12 Fashion Red - 50CT',          'shape_label' => 'Round', 'size_resolved' => 'R-12', 'texture_resolved' => 'Fashion (S)', 'color_resolved' => 'Fashion Red',         'packaging' => 'Loose', 'count_per_bag' => 50],
            // Crystal jewel-tone (added in PR #42)
            ['warehouse_sku' => '20000003', 'raw_name' => 'R-12 Crystal Red - 50CT',          'shape_label' => 'Round', 'size_resolved' => 'R-12', 'texture_resolved' => 'Crystal (S)', 'color_resolved' => 'Crystal Red',         'packaging' => 'Loose', 'count_per_bag' => 50],
            // Pastel Dusk multi-word texture
            ['warehouse_sku' => '20000004', 'raw_name' => 'R-5 Pastel Dusk Blue - 100CT',     'shape_label' => 'Round', 'size_resolved' => 'R-5',  'texture_resolved' => 'Pastel Dusk (S)', 'color_resolved' => 'Pastel Dusk Blue', 'packaging' => 'Loose', 'count_per_bag' => 100],
            // Satin (Sempertex Colombia line added with the SKU import)
            ['warehouse_sku' => '20000005', 'raw_name' => 'LOL-12 Satin Pearl - 20CT',        'shape_label' => 'Link',  'size_resolved' => 'LOL-12', 'texture_resolved' => 'Satin (S)', 'color_resolved' => 'Satin Pearl',         'packaging' => 'Loose', 'count_per_bag' => 20],
            // Heart shape (C-12)
            ['warehouse_sku' => '20000006', 'raw_name' => 'C-12 Fashion Red - 50CT',          'shape_label' => 'Heart', 'size_resolved' => 'C-12', 'texture_resolved' => 'Fashion (S)', 'color_resolved' => 'Fashion Red',         'packaging' => 'Loose', 'count_per_bag' => 50],
            // Modeling twist (T260 → 260-S, the now-corrected Non-round shape)
            ['warehouse_sku' => '20000007', 'raw_name' => '260-S Fashion Red - 100CT',        'shape_label' => 'Modeling', 'size_resolved' => '260-S', 'texture_resolved' => 'Fashion (S)', 'color_resolved' => 'Fashion Red',      'packaging' => 'Loose', 'count_per_bag' => 100],
        ];
    }

    public function test_dry_run_reports_inserts_without_writing(): void
    {
        $this->assertSame(0, Sku::where('brand_id', $this->sempertex()->id)->count());

        $this->artisan('catalog:import-sempertex', ['--path' => $this->jsonPath])
            ->expectsOutputToContain('Inserts: 7')
            ->assertSuccessful();

        $this->assertSame(0, Sku::where('brand_id', $this->sempertex()->id)->count());
    }

    public function test_execute_writes_all_rows(): void
    {
        $this->artisan('catalog:import-sempertex', ['--path' => $this->jsonPath, '--execute' => true])
            ->assertSuccessful();

        $this->assertSame(7, Sku::where('brand_id', $this->sempertex()->id)->count());
    }

    public function test_modeling_sku_resolves_to_corrected_260_s_shape(): void
    {
        $this->artisan('catalog:import-sempertex', ['--path' => $this->jsonPath, '--execute' => true])
            ->assertSuccessful();

        $sku = Sku::where('warehouse_sku', '20000007')->firstOrFail();

        $this->assertSame('260-S', $sku->balloonSize->name);
        $this->assertSame('Non-round', $sku->balloonSize->shape->name, 'SempertexBalloonSizeSeeder should have flipped 260-S from Heart to Non-round');
    }

    public function test_packaging_count_is_preserved(): void
    {
        $this->artisan('catalog:import-sempertex', ['--path' => $this->jsonPath, '--execute' => true])
            ->assertSuccessful();

        $loose = PackagingType::where('name', 'Loose')->firstOrFail();

        $this->assertSame(12, (int) Sku::where('warehouse_sku', '20000001')->firstOrFail()->default_count_per_bag);
        $this->assertSame(50, (int) Sku::where('warehouse_sku', '20000002')->firstOrFail()->default_count_per_bag);
        $this->assertSame($loose->id, Sku::where('warehouse_sku', '20000001')->firstOrFail()->packaging_id);
    }

    public function test_identical_skus_pivot_links_packaging_variants(): void
    {
        $this->artisan('catalog:import-sempertex', ['--path' => $this->jsonPath, '--execute' => true])
            ->assertSuccessful();

        $a = Sku::where('warehouse_sku', '20000001')->firstOrFail();
        $b = Sku::where('warehouse_sku', '20000002')->firstOrFail();

        $this->assertTrue($a->identicalSkus()->where('skus.id', $b->id)->exists(), 'A → B link');
        $this->assertTrue($b->identicalSkus()->where('skus.id', $a->id)->exists(), 'B → A reciprocal link');
    }

    public function test_execute_is_idempotent(): void
    {
        $this->artisan('catalog:import-sempertex', ['--path' => $this->jsonPath, '--execute' => true])->assertSuccessful();
        $this->artisan('catalog:import-sempertex', ['--path' => $this->jsonPath, '--execute' => true])->assertSuccessful();

        $this->assertSame(7, Sku::where('brand_id', $this->sempertex()->id)->count());

        $a = Sku::where('warehouse_sku', '20000001')->firstOrFail();
        $this->assertSame(1, $a->identicalSkus()->count(), 'No duplicate pivot rows after second run');
    }

    public function test_unresolved_color_is_warned_and_skipped(): void
    {
        $rows = $this->fixtureRows();
        $rows[] = [
            'warehouse_sku' => '29999999', 'raw_name' => 'R-12 Fashion Unicorn - 50CT',
            'shape_label' => 'Round', 'size_resolved' => 'R-12', 'texture_resolved' => 'Fashion (S)',
            'color_resolved' => 'Fashion Unicorn', 'packaging' => 'Loose', 'count_per_bag' => 50,
        ];
        file_put_contents($this->jsonPath, json_encode($rows));

        $this->artisan('catalog:import-sempertex', ['--path' => $this->jsonPath, '--execute' => true])
            ->expectsOutputToContain("Color not found 'Fashion Unicorn'")
            ->expectsConfirmation('There are 1 warnings. Continue anyway?', 'yes')
            ->assertSuccessful();

        $this->assertNull(Sku::where('warehouse_sku', '29999999')->first());
        $this->assertSame(7, Sku::where('brand_id', $this->sempertex()->id)->count());
    }

    private function sempertex(): Brand
    {
        return Brand::where('name', 'Sempertex')->firstOrFail();
    }
}
