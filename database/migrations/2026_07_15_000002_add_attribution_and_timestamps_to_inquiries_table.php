<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inquiries', function (Blueprint $table) {
            // Attribution (P0 #5) — captured on first visit, persisted onto the inquiry
            $table->string('utm_source')->nullable()->after('locale');
            $table->string('utm_medium')->nullable()->after('utm_source');
            $table->string('utm_campaign')->nullable()->after('utm_medium');
            $table->string('utm_term')->nullable()->after('utm_campaign');
            $table->string('utm_content')->nullable()->after('utm_term');
            $table->text('landing_page')->nullable()->after('utm_content');   // first URL the buyer hit
            $table->string('referrer')->nullable()->after('landing_page');    // document.referrer host

            // Offline attribution (P0 #6)
            $table->string('referral_code')->nullable()->after('referrer');

            // Pipeline timestamps (P0 #7): received = created_at.
            $table->timestamp('quote_sent_at')->nullable()->after('quote_pdf_path');
            $table->timestamp('order_confirmed_at')->nullable()->after('quote_sent_at');

            $table->index('utm_source');
            $table->index('referral_code');
        });
    }

    public function down(): void
    {
        Schema::table('inquiries', function (Blueprint $table) {
            $table->dropIndex(['utm_source']);
            $table->dropIndex(['referral_code']);
            $table->dropColumn([
                'utm_source',
                'utm_medium',
                'utm_campaign',
                'utm_term',
                'utm_content',
                'landing_page',
                'referrer',
                'referral_code',
                'quote_sent_at',
                'order_confirmed_at',
            ]);
        });
    }
};
