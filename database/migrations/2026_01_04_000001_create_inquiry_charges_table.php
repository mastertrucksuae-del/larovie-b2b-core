<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiry_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inquiry_id')->constrained()->cascadeOnDelete();
            $table->string('label');            // e.g. Shipping, Parking, Handling
            $table->decimal('amount', 10, 2)->default(0);
            // Billable charges are added to the customer quote + PDF;
            // non-billable ones are internal cost tracking only.
            $table->boolean('is_billable')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiry_charges');
    }
};
