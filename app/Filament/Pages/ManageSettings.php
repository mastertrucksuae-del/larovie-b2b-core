<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageSettings extends Page
{
    protected string $view = 'filament.pages.manage-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $title = 'Settings';

    protected static ?int $navigationSort = 9;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(Setting::current()->attributesToArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Branding')
                    ->columns(2)
                    ->schema([
                        TextInput::make('company_name')->required(),
                        TextInput::make('legal_entity_name')
                            ->label('Registered legal entity name')
                            ->helperText('Shown in the footer & Contact page for buyer credibility.'),
                        ColorPicker::make('brand_color'),
                        FileUpload::make('logo_path')
                            ->label('Logo')
                            ->image()
                            ->disk('public')
                            ->directory('branding')
                            ->columnSpanFull(),
                        TextInput::make('company_email')->email(),
                        TextInput::make('company_phone')->label('Phone (tap-to-call)'),
                        TextInput::make('company_whatsapp')
                            ->label('WhatsApp Business number')
                            ->helperText('Used for the wa.me tap-to-chat links. Falls back to phone if empty.'),
                        Textarea::make('company_address')->rows(2)->columnSpanFull(),
                        TextInput::make('trn')->label('Tax registration number (TRN)'),
                        TextInput::make('trade_licence_number')->label('Trade licence number'),
                    ]),

                Section::make('Contact & location')
                    ->columns(2)
                    ->schema([
                        TextInput::make('contact_hours')
                            ->label('Business hours')
                            ->placeholder('Sun–Thu, 9am–6pm GST')
                            ->columnSpanFull(),
                        Textarea::make('google_maps_embed')
                            ->label('Google Maps embed URL')
                            ->helperText('The map "src" URL from Google Maps → Share → Embed a map. Rendered on the Contact page.')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Section::make('Authenticity guarantee')
                    ->description('Founder-approved copy for the Authenticity page. State only what is verifiably true.')
                    ->schema([
                        Textarea::make('authenticity_statement_en')->label('English')->rows(4),
                        Textarea::make('authenticity_statement_ar')->label('Arabic')->rows(4),
                    ]),

                Section::make('Measurement & notifications')
                    ->columns(2)
                    ->schema([
                        TextInput::make('notification_email')
                            ->label('New-inquiry alert email')
                            ->email()
                            ->helperText('Instant email on every new inquiry. Falls back to the company email.'),
                        TextInput::make('ga4_measurement_id')
                            ->label('GA4 Measurement ID')
                            ->placeholder('G-XXXXXXXXXX')
                            ->helperText('The gtag.js snippet only loads when this is set.'),
                    ]),

                Section::make('Search indexing')
                    ->description('Keep OFF until the trust & contact pages are live and founder-approved. When ON, the storefront becomes indexable and appears in the sitemap.')
                    ->schema([
                        Toggle::make('search_indexing_enabled')
                            ->label('Allow search engines to index the storefront'),
                    ]),

                Section::make('Quote configuration')
                    ->columns(2)
                    ->schema([
                        TextInput::make('default_currency')->default('AED')->maxLength(3),
                        TextInput::make('quote_validity_days')->numeric()->minValue(1)->default(14),
                        Textarea::make('quote_terms')->rows(3)->columnSpanFull(),
                        Textarea::make('quote_footer_note')->rows(2)->columnSpanFull(),
                    ]),

                Section::make('WhatsApp message templates')
                    ->description('Placeholders: {customer_name} {reference} {quote_number} {quote_link}')
                    ->schema([
                        Textarea::make('whatsapp_message_template_en')->label('English')->rows(3),
                        Textarea::make('whatsapp_message_template_ar')->label('Arabic')->rows(3),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $setting = Setting::current();
        $setting->update($data);
        Setting::clearCache();

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }
}
