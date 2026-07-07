<?php

namespace Database\Factories;

use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        $size = $this->faker->randomElement(['30ml', '50ml', '100ml']);
        $shade = $this->faker->randomElement(['Rose', 'Nude', 'Amber', 'Musk']);

        return [
            'shopify_variant_id' => $this->faker->unique()->numberBetween(10_000_000, 99_999_999),
            'sku' => strtoupper($this->faker->bothify('LRV-###-??')),
            'title' => "{$size} / {$shade}",
            'options' => [
                ['name' => 'Size', 'value' => $size],
                ['name' => 'Shade', 'value' => $shade],
            ],
            'image_url' => 'https://picsum.photos/seed/'.$this->faker->unique()->numberBetween(1, 99999).'/600/600',
            'inventory_quantity' => $this->faker->numberBetween(0, 500),
            'wholesale_price' => $this->faker->optional(0.7)->randomFloat(2, 20, 300),
            'moq' => $this->faker->optional(0.4)->randomElement([6, 12, 24]),
            'is_visible' => true,
            'is_archived' => false,
        ];
    }

    public function priced(): static
    {
        return $this->state(fn () => ['wholesale_price' => $this->faker->randomFloat(2, 20, 300)]);
    }

    public function priceOnRequest(): static
    {
        return $this->state(fn () => ['wholesale_price' => null]);
    }
}
