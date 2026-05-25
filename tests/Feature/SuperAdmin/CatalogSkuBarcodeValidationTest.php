<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Brand;
use App\Models\Sku;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogSkuBarcodeValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private Brand $brand;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->superAdmin()->create([
            'email_verified_at' => now(),
        ]);
        $this->brand = Brand::factory()->create();
    }

    public function test_store_accepts_a_valid_upc(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.catalog.skus.store'), [
                'name' => 'Valid SKU',
                'brand_id' => $this->brand->id,
                'upc' => '012345678905',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('skus', ['name' => 'Valid SKU', 'upc' => '012345678905']);
    }

    public function test_store_rejects_a_wrong_length_upc(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.catalog.skus.store'), [
                'name' => 'Bad Length',
                'brand_id' => $this->brand->id,
                'upc' => '12345',
            ])
            ->assertSessionHasErrors('upc');

        $this->assertDatabaseMissing('skus', ['name' => 'Bad Length']);
    }

    public function test_store_rejects_an_invalid_check_digit(): void
    {
        // Length 12 (valid GTIN-12), but the trailing digit is wrong.
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.catalog.skus.store'), [
                'name' => 'Bad Check Digit',
                'brand_id' => $this->brand->id,
                'upc' => '012345678900',
            ])
            ->assertSessionHasErrors('upc');

        $this->assertDatabaseMissing('skus', ['name' => 'Bad Check Digit']);
    }

    public function test_store_normalizes_separators_in_stored_value(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.catalog.skus.store'), [
                'name' => 'Dashy Upc',
                'brand_id' => $this->brand->id,
                'upc' => '012-345-678905',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('skus', ['name' => 'Dashy Upc', 'upc' => '012345678905']);
    }

    public function test_store_accepts_null_upc(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.catalog.skus.store'), [
                'name' => 'No Upc',
                'brand_id' => $this->brand->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('skus', ['name' => 'No Upc', 'upc' => null]);
    }

    public function test_update_rejects_an_invalid_check_digit(): void
    {
        $sku = Sku::factory()->create(['brand_id' => $this->brand->id, 'upc' => '012345678905']);

        $this->actingAs($this->superAdmin)
            ->patch(route('super-admin.catalog.skus.update', $sku), [
                'name' => $sku->name,
                'brand_id' => $this->brand->id,
                'upc' => '719784100040',
            ])
            ->assertSessionHasErrors('upc');

        // Original value preserved on the failed update.
        $this->assertSame('012345678905', $sku->fresh()->upc);
    }

    public function test_update_accepts_corrected_upc(): void
    {
        $sku = Sku::factory()->create(['brand_id' => $this->brand->id, 'upc' => '012345678905']);

        $this->actingAs($this->superAdmin)
            ->patch(route('super-admin.catalog.skus.update', $sku), [
                'name' => $sku->name,
                'brand_id' => $this->brand->id,
                'upc' => '719784100041',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('719784100041', $sku->fresh()->upc);
    }

    public function test_ean_field_also_validates(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.catalog.skus.store'), [
                'name' => 'Bad Ean',
                'brand_id' => $this->brand->id,
                'ean' => '4006381333930',
            ])
            ->assertSessionHasErrors('ean');
    }
}
