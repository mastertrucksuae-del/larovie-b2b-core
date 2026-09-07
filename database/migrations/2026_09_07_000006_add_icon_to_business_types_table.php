<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An icon per business type chip.
 *
 * Stored as a key into App\Support\BusinessTypeIcons rather than raw SVG: the
 * value is rendered inline on a public page, so free-text markup here would be
 * an XSS hole.
 *
 * The six seeded types get a matching icon so the section looks finished
 * immediately; anything added later falls back to the storefront icon until an
 * admin picks one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_types', function (Blueprint $table) {
            $table->string('icon')->nullable()->after('name_ar');
        });

        $defaults = [
            'Beauty retailers' => 'storefront',
            'Pharmacies' => 'pharmacy',
            'Salons and spas' => 'sparkles',
            'E-commerce stores' => 'cart',
            'Clinics' => 'clinic',
            'Distributors' => 'truck',
        ];

        foreach ($defaults as $name => $icon) {
            DB::table('business_types')->where('name_en', $name)->update(['icon' => $icon]);
        }
    }

    public function down(): void
    {
        Schema::table('business_types', function (Blueprint $table) {
            $table->dropColumn('icon');
        });
    }
};
