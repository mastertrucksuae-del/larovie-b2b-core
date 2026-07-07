<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('shopify_variant_id')->unique()->index();
            $table->string('sku')->nullable()->index();
            $table->string('title')->nullable(); // e.g. "50ml / Rose"
            $table->json('options')->nullable();
            $table->text('image_url')->nullable();
            $table->integer('inventory_quantity')->nullable();

            // Admin-owned fields — never overwritten by sync
            $table->decimal('wholesale_price', 10, 2)->nullable();
            $table->unsignedInteger('moq')->nullable();
            $table->boolean('is_visible')->default(true);

            $table->boolean('is_archived')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
