<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Real categories, imported from Shopify collections.
 *
 * Replaces the keyword-guessing stopgap in App\Support\DerivedCategories, which
 * inferred a category from words in the product title because nothing in the
 * data carried one: `product_type` is empty for most products and holds a unique
 * marketing sentence for the rest.
 *
 * Same ownership split as products: Shopify owns the title, handle and artwork
 * and overwrites them on every sync; the admin owns visibility, ordering, the
 * Arabic name and an optional image override, and those are never touched by a
 * sync. A collection that disappears from Shopify is archived, never deleted, so
 * an accidental deletion upstream cannot silently drop a section of the site.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();

            // Null for a category created by hand in the panel.
            $table->unsignedBigInteger('shopify_collection_id')->nullable()->unique();
            $table->string('handle')->nullable();

            // Shopify-owned
            $table->string('title');
            $table->text('image_url')->nullable();

            // Admin-owned — never overwritten by a sync
            $table->string('title_ar')->nullable();
            $table->string('image_path')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(false);

            $table->boolean('is_archived')->default(false);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['is_visible', 'is_archived', 'sort_order']);
        });

        Schema::create('category_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            // A product belongs to a collection once; the sync re-imports freely.
            $table->unique(['category_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_product');
        Schema::dropIfExists('categories');
    }
};
