<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets the founder turn manual KYC review off.
 *
 * Defaults to true, which is the behaviour that already shipped: registrations
 * land as `pending` and wait for an admin. Switched off, a new account is
 * approved the moment it registers and can price immediately — the right
 * setting when the wholesale relationship is established elsewhere and the
 * queue is only friction.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('require_account_review')->default(true)->after('search_indexing_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('require_account_review');
        });
    }
};
