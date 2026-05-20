<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\BalloonSize;
use App\Models\Brand;
use App\Models\BrandGs1Prefix;
use App\Models\Color;
use App\Models\ColorFamily;
use App\Models\ColorTranslation;
use App\Models\Material;
use App\Models\MaterialTranslation;
use App\Models\PackagingType;
use App\Models\PriceCode;
use App\Models\PrintColor;
use App\Models\PrintSide;
use App\Models\Shape;
use App\Models\ShapeTranslation;
use App\Models\Size;
use App\Models\Sku;
use App\Models\Texture;
use App\Models\TextureFamily;
use App\Models\TextureTranslation;
use App\Models\Theme;
use App\Models\ThemeTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogFactoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_catalog_factories_persist_a_model(): void
    {
        $this->assertTrue(Brand::factory()->create()->exists);
        $this->assertTrue(Sku::factory()->create()->exists);
        $this->assertTrue(TextureFamily::factory()->create()->exists);
        $this->assertTrue(Texture::factory()->create()->exists);
        $this->assertTrue(Material::factory()->create()->exists);
        $this->assertTrue(ColorFamily::factory()->create()->exists);
        $this->assertTrue(Color::factory()->create()->exists);
        $this->assertTrue(Shape::factory()->create()->exists);
        $this->assertTrue(Size::factory()->create()->exists);
        $this->assertTrue(BalloonSize::factory()->create()->exists);
        $this->assertTrue(Theme::factory()->create()->exists);
        $this->assertTrue(BrandGs1Prefix::factory()->create()->exists);
        $this->assertTrue(PackagingType::factory()->create()->exists);
        $this->assertTrue(PriceCode::factory()->create()->exists);
        $this->assertTrue(PrintColor::factory()->create()->exists);
        $this->assertTrue(PrintSide::factory()->create()->exists);
        $this->assertTrue(TextureTranslation::factory()->create()->exists);
        $this->assertTrue(MaterialTranslation::factory()->create()->exists);
        $this->assertTrue(ColorTranslation::factory()->create()->exists);
        $this->assertTrue(ShapeTranslation::factory()->create()->exists);
        $this->assertTrue(ThemeTranslation::factory()->create()->exists);
    }
}
