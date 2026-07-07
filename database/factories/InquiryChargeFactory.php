<?php

namespace Database\Factories;

use App\Models\InquiryCharge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InquiryCharge>
 */
class InquiryChargeFactory extends Factory
{
    protected $model = InquiryCharge::class;

    public function definition(): array
    {
        return [
            'label' => $this->faker->randomElement(['Shipping', 'Handling', 'Insurance']),
            'type' => \App\Models\InquiryCharge::TYPE_FIXED,
            'amount' => $this->faker->randomFloat(2, 10, 200),
            'is_billable' => true,
        ];
    }

    public function internal(): static
    {
        return $this->state(fn () => ['is_billable' => false, 'label' => 'Parking']);
    }

    public function percent(float $percent): static
    {
        return $this->state(fn () => ['type' => \App\Models\InquiryCharge::TYPE_PERCENT, 'amount' => $percent]);
    }
}
