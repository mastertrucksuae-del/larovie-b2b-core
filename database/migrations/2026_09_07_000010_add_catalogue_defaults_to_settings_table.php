<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Moves the catalogue/sync knobs out of .env and into the dashboard.
 *
 * These were env-only, which meant changing the default MOQ needed a deploy and
 * a server login — for a number the founder is the only person qualified to set.
 *
 * All three are nullable and fall back to the existing config values, so a fresh
 * install behaves exactly as before and `.env` keeps working as the default.
 *
 * Deliberately NOT moved: the Shopify admin token, API key, API secret and
 * webhook secret. Those are credentials. Putting them in a database row that the
 * admin panel renders would widen their blast radius from "whoever can read the
 * server's .env" to "whoever can reach an admin session", and they are rotated
 * by Shopify rather than chosen by the founder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->unsignedInteger('default_moq')->nullable()->after('quote_footer_note');
            $table->unsignedInteger('sync_page_size')->nullable()->after('default_moq');
            $table->unsignedInteger('sync_cost_floor')->nullable()->after('sync_page_size');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['default_moq', 'sync_page_size', 'sync_cost_floor']);
        });
    }
};
