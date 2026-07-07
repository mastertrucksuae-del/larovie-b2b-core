<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inquiry_charges', function (Blueprint $table) {
            // 'fixed' = amount is a money value; 'percent' = amount is a % of the products subtotal.
            $table->string('type', 10)->default('fixed')->after('label');
        });
    }

    public function down(): void
    {
        Schema::table('inquiry_charges', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
