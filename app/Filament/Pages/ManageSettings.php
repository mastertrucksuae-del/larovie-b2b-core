<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
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
                        ColorPicker::make('brand_color'),
                        FileUpload::make('logo_path')
                            ->label('Logo')
                            ->image()
                            ->disk('public')
                            ->directory('branding')
                            ->columnSpanFull(),
                        TextInput::make('company_email')->email(),
                        TextInput::make('company_phone'),
                        Textarea::make('company_address')->rows(2)->columnSpanFull(),
                        TextInput::make('trn')->label('Tax registration number (TRN)'),
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
