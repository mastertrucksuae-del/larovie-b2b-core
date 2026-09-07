<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets the founder hand-pick which categories tile on the homepage.
 *
 * Empty means "choose automatically" — the visible categories in their admin
 * order — so the section keeps working before anyone touches this, exactly like
 * the featured product and brand pickers beside it.
 *
 * Stored as category ids rather than names: unlike brands, a category is a real
 * record here, so the id is the stable key and a rename cannot break the pick.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->json('homepage_featured_category_ids')->nullable()->after('homepage_featured_brands');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('homepage_featured_category_ids');
        });
    }
};
