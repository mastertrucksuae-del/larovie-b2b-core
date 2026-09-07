<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets the admin reorder the homepage's middle sections.
 *
 * Stored as an ordered list of section keys, e.g. ["featured","brands",…].
 * Null means "shipped order". The renderer treats the stored list as a
 * preference rather than a spec: unknown keys are ignored and any section
 * missing from it still renders, appended in its default position, so adding a
 * new section later cannot make it invisible on existing installs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->json('homepage_section_order')->nullable()->after('homepage_sections');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('homepage_section_order');
        });
    }
};
