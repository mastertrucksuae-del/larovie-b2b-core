<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Auto-detected on import from title/type/tags, then admin-editable.
            // Bundles/kits/sets are hidden from the solo-product catalogue.
            $table->boolean('is_bundle')->default(false)->after('product_type')->index();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['is_bundle']);
            $table->dropColumn('is_bundle');
        });
    }
};
