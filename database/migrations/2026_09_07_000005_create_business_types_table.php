<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The "Business types we serve" list, managed by hand in the admin panel.
 *
 * Manual on purpose: unlike categories there is nothing in Shopify to import
 * this from — it is a statement about who the wholesaler sells to, which only
 * the founder can answer.
 *
 * Seeded with the six that were previously hard-coded in the language files, so
 * the homepage looks identical the moment this runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_types', function (Blueprint $table) {
            $table->id();
            $table->string('name_en');
            $table->string('name_ar')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->index(['is_visible', 'sort_order']);
        });

        $now = now();
        $seed = [
            ['Beauty retailers', 'متاجر التجميل'],
            ['Pharmacies', 'الصيدليات'],
            ['Salons and spas', 'الصالونات والمنتجعات'],
            ['E-commerce stores', 'المتاجر الإلكترونية'],
            ['Clinics', 'العيادات'],
            ['Distributors', 'الموزعون'],
        ];

        DB::table('business_types')->insert(
            collect($seed)->map(fn (array $row, int $i) => [
                'name_en' => $row[0],
                'name_ar' => $row[1],
                'sort_order' => $i,
                'is_visible' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all()
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('business_types');
    }
};
