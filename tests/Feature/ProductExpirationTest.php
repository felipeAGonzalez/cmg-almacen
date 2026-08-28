<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductExpirationTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_can_be_created_with_expiration_control_enabled_or_disabled(): void
    {
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->post(route('products.store'), $this->payload('EXP-001', true))
            ->assertSessionHasNoErrors();

        $this->post(route('products.store'), $this->payload('EXP-002', false))
            ->assertSessionHasNoErrors();

        $this->assertTrue(Product::query()->where('code', 'EXP-001')->firstOrFail()->requires_expiration);
        $this->assertFalse(Product::query()->where('code', 'EXP-002')->firstOrFail()->requires_expiration);
    }

    public function test_requires_expiration_defaults_to_false_and_is_cast_as_boolean(): void
    {
        $product = Product::factory()->create();

        $this->assertFalse($product->requires_expiration);
        $this->assertIsBool($product->requires_expiration);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'requires_expiration' => false,
        ]);
    }

    public function test_product_can_toggle_expiration_control_in_both_directions(): void
    {
        $administrator = User::factory()->administrator()->create();
        $product = Product::factory()->create(['requires_expiration' => true]);

        $this->actingAs($administrator)
            ->put(route('products.update', $product), $this->payload('TOGGLE-001', false))
            ->assertSessionHasNoErrors();
        $this->assertFalse($product->fresh()->requires_expiration);

        $this->put(route('products.update', $product), $this->payload('TOGGLE-001', true))
            ->assertSessionHasNoErrors();
        $this->assertTrue($product->fresh()->requires_expiration);
    }

    public function test_missing_checkbox_becomes_false_but_invalid_values_are_rejected(): void
    {
        $administrator = User::factory()->administrator()->create();
        $product = Product::factory()->create(['requires_expiration' => true]);
        $payload = $this->payload('MISSING-001', false);
        unset($payload['requires_expiration']);

        $this->actingAs($administrator)
            ->put(route('products.update', $product), $payload)
            ->assertSessionHasNoErrors();
        $this->assertFalse($product->fresh()->requires_expiration);

        $this->put(route('products.update', $product), [
            ...$this->payload('MISSING-001', false),
            'requires_expiration' => 'invalid',
        ])->assertSessionHasErrors('requires_expiration');
    }

    public function test_product_forms_and_index_render_expiration_control(): void
    {
        $administrator = User::factory()->administrator()->create();
        $enabled = Product::factory()->create(['name' => 'Producto con caducidad', 'requires_expiration' => true]);
        Product::factory()->create(['name' => 'Producto sin caducidad', 'requires_expiration' => false]);

        $this->actingAs($administrator)
            ->get(route('products.create'))
            ->assertOk()
            ->assertSee('Controlar caducidad')
            ->assertSee('name="requires_expiration" value="0"', false)
            ->assertSee('type="checkbox"', false);

        $this->get(route('products.edit', $enabled))
            ->assertOk()
            ->assertSee('id="requires_expiration"', false)
            ->assertSee('checked', false);

        $this->get(route('products.index'))
            ->assertOk()
            ->assertSee('Caducidad')
            ->assertSee('Producto con caducidad')
            ->assertSee('Producto sin caducidad')
            ->assertSee('Sí')
            ->assertSee('No');
    }

    /** @return array<string, mixed> */
    private function payload(string $code, bool $requiresExpiration): array
    {
        return [
            'name' => 'Producto de prueba',
            'code' => $code,
            'barcode' => null,
            'unit_id' => Unit::factory()->create()->id,
            'category_id' => Category::factory()->create()->id,
            'brand_id' => Brand::factory()->create()->id,
            'description' => null,
            'requires_expiration' => $requiresExpiration,
        ];
    }
}
