<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Admin user for the Filament panel.
        User::updateOrCreate(
            ['email' => 'admin@larovie.ae'],
            [
                'name' => 'Larovie Admin',
                'password' => Hash::make('password'),
            ]
        );

        // Default settings row (branding + quote + WhatsApp templates).
        Setting::updateOrCreate(['id' => 1], [
            'company_name' => 'Larovié',
            'brand_color' => '#3E2340',
            'company_email' => 'wholesale@larovie.ae',
            'company_phone' => '+971 4 000 0000',
            'company_address' => 'Dubai, United Arab Emirates',
            'default_currency' => 'AED',
            'quote_validity_days' => 14,
            'quote_terms' => 'Prices are quoted in AED and are exclusive of delivery unless stated. '
                .'Quotation valid until the date shown. Subject to stock availability.',
            'quote_footer_note' => 'Thank you for your interest in Larovie wholesale.',
            'whatsapp_message_template_en' => 'Hello {customer_name}, thank you for your inquiry ({reference}). '
                .'Your quote {quote_number} is ready: {quote_link}',
            'whatsapp_message_template_ar' => 'مرحباً {customer_name}، شكراً لاستفسارك ({reference}). '
                .'عرض السعر الخاص بك رقم {quote_number} جاهز: {quote_link}',
        ]);

        $this->call([
            CatalogueSeeder::class,
        ]);
    }
}
