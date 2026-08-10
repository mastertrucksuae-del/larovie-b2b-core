<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Go-live: the trust & contact pages shipped, so the indexing gate opens.
 *
 * Until now `search_indexing_enabled` defaulted to false, which served
 * `noindex, nofollow`, a `Disallow: /` robots.txt and a 404 sitemap. This flips
 * the existing settings row on and makes ON the default for fresh installs.
 * The founder can still switch it back off in Admin → Settings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('search_indexing_enabled')->default(true)->change();
        });

        DB::table('settings')->update(['search_indexing_enabled' => true]);
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('search_indexing_enabled')->default(false)->change();
        });

        DB::table('settings')->update(['search_indexing_enabled' => false]);
    }
};
