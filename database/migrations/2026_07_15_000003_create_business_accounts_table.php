<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Lightweight KYC business accounts (P1 #10). Approved status is the future
    // gate for pricing visibility (P2 price bands).
    public function up(): void
    {
        Schema::create('business_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('contact_person');
            $table->string('email')->unique();
            $table->string('phone');
            $table->string('password');

            // KYC document
            $table->string('trade_licence_number')->nullable();
            $table->text('trade_licence_path')->nullable(); // uploaded file on the public disk

            // Approval workflow: pending / approved / rejected
            $table->string('status')->default('pending')->index();
            $table->text('review_notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('locale', 5)->default('en');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_accounts');
    }
};
