<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiries', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique(); // e.g. LRV-2026-0001
            $table->string('status')->default('new_inquiry'); // new_inquiry, responding, prices_filled, quote_sent

            // Customer
            $table->string('customer_name');
            $table->string('customer_mobile'); // normalized E.164
            $table->boolean('is_whatsapp')->default(false);
            $table->string('customer_email')->nullable();
            $table->string('customer_company')->nullable();
            $table->text('customer_message')->nullable();

            // Internal
            $table->text('admin_notes')->nullable();

            // Quote
            $table->string('quote_number')->nullable();
            $table->date('quote_valid_until')->nullable();
            $table->string('currency', 3)->default('AED');
            $table->decimal('quoted_subtotal', 10, 2)->nullable();
            $table->decimal('quoted_total', 10, 2)->nullable();
            $table->string('locale', 5)->default('en'); // en / ar
            $table->text('quote_pdf_path')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiries');
    }
};
