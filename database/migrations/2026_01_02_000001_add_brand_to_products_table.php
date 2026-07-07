<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Brand name resolved from the Shopify "Brands" metaobject
            // (via each brand's featured_products list). Shopify-owned.
            $table->string('brand')->nullable()->after('vendor')->index();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['brand']);
            $table->dropColumn('brand');
        });
    }
};
