<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ties an inquiry to the business account that submitted it.
 *
 * Inquiries predate accounts: the cart is session-based and guests submit
 * without signing in, so the customer was only ever free text. That makes
 * per-customer reporting guesswork. From now on a signed-in buyer's inquiry
 * carries their account id; historical rows stay null and are matched on email
 * instead, which is unique on `business_accounts`.
 *
 * Nullable on purpose — guest inquiries remain first-class, and the column is
 * set null rather than cascading if an account is ever deleted, because losing
 * the order history with the account would be worse than an orphan row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inquiries', function (Blueprint $table) {
            $table->foreignId('business_account_id')
                ->nullable()
                ->after('id')
                ->constrained('business_accounts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inquiries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('business_account_id');
        });
    }
};
