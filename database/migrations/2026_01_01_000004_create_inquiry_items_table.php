<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiry_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inquiry_id')->constrained()->cascadeOnDelete();
            // Nullable + nullOnDelete: variant may be archived/removed later,
            // but the snapshot fields keep the line item accurate.
            $table->foreignId('product_variant_id')->nullable()->nullOnDelete();

            // Snapshotted at submission time — do NOT rely on live product data
            $table->string('product_title');
            $table->string('variant_title')->nullable();
            $table->string('sku')->nullable();
            $table->text('image_url')->nullable();

            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 10, 2)->nullable();  // filled by admin
            $table->decimal('line_total', 10, 2)->nullable();  // quantity * unit_price

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiry_items');
    }
};
