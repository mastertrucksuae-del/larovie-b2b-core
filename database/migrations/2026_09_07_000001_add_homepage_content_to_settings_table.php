<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Makes the homepage editable from the admin panel.
 *
 * The copy lives in one JSON column rather than ~110 `_en`/`_ar` columns: the
 * homepage carries 50-odd strings in two languages, and a column per string
 * would need a migration every time a section gains a line. Anything left blank
 * falls back to the shipped translation, so the JSON only ever holds real
 * overrides and an empty column still renders a complete page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            // {"en": {key: value, …}, "ar": {key: value, …}} — overrides only.
            $table->json('homepage_content')->nullable()->after('authenticity_statement_ar');

            // {"categories": true, "brands": false, …} — per-section visibility.
            $table->json('homepage_sections')->nullable()->after('homepage_content');

            $table->string('homepage_hero_image_path')->nullable()->after('homepage_sections');
            $table->unsignedTinyInteger('homepage_featured_count')->default(8)->after('homepage_hero_image_path');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'homepage_content',
                'homepage_sections',
                'homepage_hero_image_path',
                'homepage_featured_count',
            ]);
        });
    }
};
