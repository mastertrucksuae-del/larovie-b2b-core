<?php

namespace Database\Factories;

use App\Models\InquiryItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InquiryItem>
 */
class InquiryItemFactory extends Factory
{
    protected $model = InquiryItem::class;

    public function definition(): array
    {
        $qty = $this->faker->randomElement([6, 12, 24]);

        return [
            'product_title' => ucwords($this->faker->words(2, true)),
            'variant_title' => $this->faker->randomElement(['50ml / Rose', '30ml / Nude', '100ml / Amber']),
            'sku' => strtoupper($this->faker->bothify('LRV-###-??')),
            'image_url' => 'https://picsum.photos/seed/'.$this->faker->numberBetween(1, 99999).'/300/300',
            'quantity' => $qty,
            'unit_price' => null,
            'line_total' => null,
        ];
    }
}
