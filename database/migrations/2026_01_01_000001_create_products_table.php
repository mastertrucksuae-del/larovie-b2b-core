<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shopify_product_id')->unique()->index();
            $table->string('title');
            $table->string('handle')->nullable();
            $table->longText('description')->nullable();
            $table->string('vendor')->nullable();
            $table->string('product_type')->nullable();
            $table->json('tags')->nullable();
            $table->text('featured_image_url')->nullable();
            $table->string('shopify_status')->nullable(); // active/draft/archived

            // Admin-owned fields — never overwritten by sync
            $table->boolean('is_visible')->default(false);
            $table->unsignedInteger('moq')->nullable();

            $table->timestamp('synced_at')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->timestamps();

            $table->index('product_type');
            $table->index('is_visible');
            $table->index('is_archived');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
