<?php

namespace Tests\Feature\Onboarding;

use App\Models\BalloonSize;
use App\Models\Brand;
use App\Models\Color;
use App\Models\Shape;
use App\Models\Size;
use App\Models\Sku;
use App\Services\OnboardingSeedResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OnboardingSeedResolverTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, Size> */
    private array $sizes = [];

    /** @var array<string, Shape> */
    private array $shapes = [];

    public function test_it_resolves_matched_no_match_and_brand_missing_statuses(): void
    {
        $stx = Brand::factory()->create(['name' => 'Sempertex']);
        Brand::factory()->create(['name' => 'Kalisan']); // exists, but no matching SKU

        $sku = $this->sku($stx, '260', 'Non-round', 'Fashion White', 50);

        $spec = [
            'defaults' => ['count_per_bag' => [100, 50]],
            'items' => [[
                'key' => 'white-260',
                'size' => '260',
                'shape' => 'Non-round',
                'bags' => 4,
                'colors' => [
                    'Sempertex' => 'Fashion White',
                    'Kalisan' => 'White',   // brand exists, no such SKU
                    'Gemar' => 'White',     // brand not in catalog at all
                ],
            ]],
        ];

        $rows = collect(app(OnboardingSeedResolver::class)->resolve($spec))->keyBy('brand');

        $this->assertSame('matched', $rows['Sempertex']['status']);
        $this->assertSame($sku->id, $rows['Sempertex']['sku_id']);
        $this->assertSame(50, $rows['Sempertex']['count_per_bag']);
        $this->assertFalse($rows['Sempertex']['count_fallback']);

        $this->assertSame('no_match', $rows['Kalisan']['status']);
        $this->assertNull($rows['Kalisan']['sku_id']);

        $this->assertSame('brand_missing', $rows['Gemar']['status']);
    }

    public function test_count_preference_picks_first_available_in_order(): void
    {
        $brand = Brand::factory()->create(['name' => 'Kalisan']);

        // Same size/shape/color, two bag counts.
        $balloonSize = $this->balloonSize($brand, '260', 'Non-round');
        $color = Color::factory()->create(['brand_id' => $brand->id, 'name' => 'White']);
        $bag50 = $this->skuFor($brand, $balloonSize, $color, 50);
        $bag100 = $this->skuFor($brand, $balloonSize, $color, 100);

        $item = ['size' => '260', 'shape' => 'Non-round', 'bags' => 4, 'colors' => ['Kalisan' => 'White']];

        $preferred100 = app(OnboardingSeedResolver::class)->resolve([
            'defaults' => ['count_per_bag' => [100, 50]],
            'items' => [$item],
        ]);
        $this->assertSame($bag100->id, $preferred100[0]['sku_id']);
        $this->assertFalse($preferred100[0]['count_fallback']);

        $preferred50 = app(OnboardingSeedResolver::class)->resolve([
            'defaults' => ['count_per_bag' => [50, 100]],
            'items' => [$item],
        ]);
        $this->assertSame($bag50->id, $preferred50[0]['sku_id']);
        $this->assertFalse($preferred50[0]['count_fallback']);
    }

    public function test_count_falls_back_to_nearest_and_flags_it(): void
    {
        $brand = Brand::factory()->create(['name' => 'Kalisan']);
        $this->sku($brand, '36-inch', 'Round', 'White', 2); // only a 2-count bag exists

        $rows = app(OnboardingSeedResolver::class)->resolve([
            'defaults' => ['count_per_bag' => [100, 50]],
            'items' => [[
                'size' => '36-inch', 'shape' => 'Round', 'bags' => 2,
                'colors' => ['Kalisan' => 'White'],
            ]],
        ]);

        $this->assertSame('matched', $rows[0]['status']);
        $this->assertSame(2, $rows[0]['count_per_bag']);
        $this->assertTrue($rows[0]['count_fallback']);
    }

    public function test_per_brand_size_override_is_applied(): void
    {
        $kal = Brand::factory()->create(['name' => 'Kalisan']);

        // Kalisan's only heart is the 11-inch; the row default size is 5-inch.
        $match = $this->sku($kal, '11-inch', 'Heart', 'Red', 25);
        $this->sku($kal, '5-inch', 'Heart', 'Red', 50); // should NOT be chosen

        $rows = app(OnboardingSeedResolver::class)->resolve([
            'defaults' => ['count_per_bag' => [50]],
            'items' => [[
                'size' => '5-inch', 'shape' => 'Heart', 'bags' => 2,
                'colors' => [
                    'Kalisan' => ['color' => 'Red', 'size' => '11-inch', 'count_per_bag' => 25],
                ],
            ]],
        ]);

        $this->assertSame('matched', $rows[0]['status']);
        $this->assertSame($match->id, $rows[0]['sku_id']);
        $this->assertSame(25, $rows[0]['count_per_bag']);
    }

    public function test_only_brands_filter_limits_resolution(): void
    {
        $stx = Brand::factory()->create(['name' => 'Sempertex']);
        $kal = Brand::factory()->create(['name' => 'Kalisan']);
        $this->sku($stx, '260', 'Non-round', 'Fashion White', 50);
        $this->sku($kal, '260', 'Non-round', 'White', 50);

        $rows = app(OnboardingSeedResolver::class)->resolve([
            'defaults' => ['count_per_bag' => [50]],
            'items' => [[
                'size' => '260', 'shape' => 'Non-round', 'bags' => 4,
                'colors' => ['Sempertex' => 'Fashion White', 'Kalisan' => 'White'],
            ]],
        ], onlyBrands: ['Kalisan']);

        $this->assertCount(1, $rows);
        $this->assertSame('Kalisan', $rows[0]['brand']);
    }

    public function test_shipped_spec_files_are_valid_and_well_formed(): void
    {
        $resolver = app(OnboardingSeedResolver::class);
        $directory = $resolver->defaultDirectory();

        $files = glob($directory.'/*.json');
        $this->assertNotEmpty($files, 'No seed-list spec files found.');

        foreach ($files as $file) {
            $decoded = json_decode((string) file_get_contents($file), true);

            $this->assertIsArray($decoded, basename($file).' is not valid JSON.');
            $this->assertNotEmpty($resolver->rolesFor($decoded), basename($file).' declares no role(s).');
            $this->assertArrayHasKey('items', $decoded, basename($file).' has no items.');

            foreach ($decoded['items'] as $i => $item) {
                foreach (['key', 'size', 'shape', 'colors'] as $required) {
                    $this->assertArrayHasKey($required, $item, basename($file)." item #{$i} missing '{$required}'.");
                }
                $this->assertNotEmpty($item['colors'], basename($file)." item '{$item['key']}' has no colors.");
            }
        }
    }

    public function test_command_resolves_a_fixture_spec(): void
    {
        $brand = Brand::factory()->create(['name' => 'Sempertex']);
        $this->sku($brand, '260', 'Non-round', 'Fashion White', 50);

        $dir = storage_path('app/testing/seed_'.Str::random(8));
        mkdir($dir, 0777, true);
        file_put_contents($dir.'/twister.json', json_encode([
            'role' => 'twister',
            'label' => 'Twister Starter Kit',
            'defaults' => ['count_per_bag' => [100, 50]],
            'items' => [[
                'key' => 'white-260', 'size' => '260', 'shape' => 'Non-round', 'bags' => 4,
                'colors' => ['Sempertex' => 'Fashion White'],
            ]],
        ]));

        try {
            $this->artisan('onboarding:seed-list', ['role' => 'twister', '--path' => $dir])
                ->assertSuccessful();
        } finally {
            array_map('unlink', glob($dir.'/*'));
            rmdir($dir);
        }
    }

    private function sku(Brand $brand, string $size, string $shape, string $color, int $count): Sku
    {
        return $this->skuFor(
            $brand,
            $this->balloonSize($brand, $size, $shape),
            Color::factory()->create(['brand_id' => $brand->id, 'name' => $color]),
            $count,
        );
    }

    private function skuFor(Brand $brand, BalloonSize $balloonSize, Color $color, int $count): Sku
    {
        return Sku::factory()->create([
            'brand_id' => $brand->id,
            'balloon_size_id' => $balloonSize->id,
            'color_id' => $color->id,
            'default_count_per_bag' => $count,
            'owned_by_business_id' => null,
        ]);
    }

    private function balloonSize(Brand $brand, string $size, string $shape): BalloonSize
    {
        return BalloonSize::factory()->create([
            'brand_id' => $brand->id,
            'size_id' => $this->sizeRow($size)->id,
            'shape_id' => $this->shapeRow($shape)->id,
        ]);
    }

    private function sizeRow(string $name): Size
    {
        return $this->sizes[$name] ??= Size::factory()->create(['name' => $name]);
    }

    private function shapeRow(string $name): Shape
    {
        return $this->shapes[$name] ??= Shape::factory()->create(['name' => $name]);
    }
}
