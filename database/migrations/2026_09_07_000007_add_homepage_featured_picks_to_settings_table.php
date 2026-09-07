<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets the founder hand-pick the homepage's featured products and brands.
 *
 * Null or empty means "choose automatically" — newest products with artwork,
 * and the brands with the deepest catalogue — so the homepage is never blank on
 * a fresh install and stays sensible if the picks are later cleared.
 *
 * Products are stored as ids, brands as names: a "brand" on the storefront is
 * `brand ?: vendor` resolved off the product row, which is what the section
 * groups and links on, so the name is the stable key rather than a brands-table
 * id that products do not reference.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->json('homepage_featured_product_ids')->nullable()->after('homepage_featured_count');
            $table->json('homepage_featured_brands')->nullable()->after('homepage_featured_product_ids');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['homepage_featured_product_ids', 'homepage_featured_brands']);
        });
    }
};
