<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Admin-uploaded image overrides. When set they take precedence over the
        // Shopify image and are preserved across re-syncs.
        Schema::table('products', function (Blueprint $table) {
            $table->text('image_path')->nullable()->after('featured_image_url');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->text('image_path')->nullable()->after('image_url');
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn('image_path'));
        Schema::table('product_variants', fn (Blueprint $t) => $t->dropColumn('image_path'));
    }
};
