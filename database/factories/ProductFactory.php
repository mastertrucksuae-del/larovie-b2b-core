<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'shopify_product_id' => $this->faker->unique()->numberBetween(1_000_000, 9_999_999),
            'title' => ucwords($name),
            'handle' => str($name)->slug(),
            'description' => '<p>'.$this->faker->paragraph().'</p>',
            'vendor' => 'Larovie',
            'product_type' => $this->faker->randomElement(['Fragrance', 'Skincare', 'Lip', 'Eyes', 'Body']),
            'tags' => $this->faker->randomElements(['new', 'bestseller', 'vegan', 'limited'], 2),
            'featured_image_url' => 'https://picsum.photos/seed/'.$this->faker->unique()->numberBetween(1, 99999).'/600/600',
            'shopify_status' => 'active',
            'is_visible' => true,
            'moq' => $this->faker->randomElement([6, 12, 24]),
            'synced_at' => now(),
            'is_archived' => false,
        ];
    }

    public function hidden(): static
    {
        return $this->state(fn () => ['is_visible' => false]);
    }
}
