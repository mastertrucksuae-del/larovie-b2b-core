<?php

namespace Database\Seeders;

use App\Models\Inquiry;
use App\Models\InquiryItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;

class CatalogueSeeder extends Seeder
{
    public function run(): void
    {
        // Sample catalogue — 12 visible products, each with 2–4 variants.
        Product::factory()
            ->count(12)
            ->create()
            ->each(function (Product $product) {
                ProductVariant::factory()
                    ->count(fake()->numberBetween(2, 4))
                    ->for($product)
                    ->create();
            });

        // A hidden product to prove visibility filtering.
        Product::factory()->hidden()->create()->each(
            fn (Product $p) => ProductVariant::factory()->count(2)->for($p)->create()
        );

        // A couple of sample inquiries with snapshotted line items.
        Inquiry::factory()
            ->count(3)
            ->create()
            ->each(function (Inquiry $inquiry) {
                InquiryItem::factory()
                    ->count(fake()->numberBetween(1, 3))
                    ->for($inquiry)
                    ->create();
            });
    }
}
