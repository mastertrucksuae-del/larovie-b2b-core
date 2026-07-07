<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();

            // Branding
            $table->string('company_name')->default('Larovie');
            $table->text('logo_path')->nullable();
            $table->string('brand_color', 20)->default('#3E2340');
            $table->text('company_address')->nullable();
            $table->string('company_email')->nullable();
            $table->string('company_phone')->nullable();
            $table->string('trn')->nullable(); // tax registration number

            // Quote config
            $table->string('default_currency', 3)->default('AED');
            $table->unsignedInteger('quote_validity_days')->default(14);
            $table->text('quote_terms')->nullable();
            $table->text('quote_footer_note')->nullable();

            // WhatsApp templates (placeholders: {customer_name} {reference} {quote_number} {quote_link})
            $table->text('whatsapp_message_template_en')->nullable();
            $table->text('whatsapp_message_template_ar')->nullable();

            // Shopify sync meta
            $table->timestamp('last_synced_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
