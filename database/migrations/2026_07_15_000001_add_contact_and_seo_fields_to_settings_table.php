<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            // Trust & contact (P0 #1, #2, #4)
            $table->string('company_whatsapp')->nullable()->after('company_phone'); // WhatsApp Business number (tap-to-chat)
            $table->string('legal_entity_name')->nullable()->after('company_name');   // registered legal entity
            $table->string('trade_licence_number')->nullable()->after('trn');         // trade licence no.
            $table->text('google_maps_embed')->nullable()->after('company_address');  // Google Maps iframe src (contact page)
            $table->string('contact_hours')->nullable()->after('google_maps_embed');  // e.g. "Sun–Thu, 9am–6pm GST"

            // Authenticity guarantee copy (P0 #3) — founder-editable, EN/AR
            $table->text('authenticity_statement_en')->nullable();
            $table->text('authenticity_statement_ar')->nullable();

            // Measurement (P0 #5, #8)
            $table->string('notification_email')->nullable();  // admin alert recipient for new inquiries
            $table->string('ga4_measurement_id')->nullable();  // GA4 "G-XXXX"; script only renders when set

            // Indexing go-live gate (P1 #9) — noindex stays until the founder flips this on
            $table->boolean('search_indexing_enabled')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'company_whatsapp',
                'legal_entity_name',
                'trade_licence_number',
                'google_maps_embed',
                'contact_hours',
                'authenticity_statement_en',
                'authenticity_statement_ar',
                'notification_email',
                'ga4_measurement_id',
                'search_indexing_enabled',
            ]);
        });
    }
};
