<?php

namespace App\Filament\Resources\BusinessAccounts\Schemas;

use App\Models\BusinessAccount;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class BusinessAccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Review')
                    ->columns(2)
                    ->schema([
                        Select::make('status')
                            ->options(BusinessAccount::STATUSES)
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Use the Approve / Reject buttons above to change status (keeps the audit trail).')
                            ->native(false),
                        Textarea::make('review_notes')
                            ->label('Review notes (shown to the applicant if rejected)')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Section::make('Applicant')
                    ->columns(2)
                    ->schema([
                        TextInput::make('company_name')->disabled(),
                        TextInput::make('contact_person')->disabled(),
                        TextInput::make('email')->disabled(),
                        TextInput::make('phone')->disabled(),
                        TextInput::make('trade_licence_number')->label('Trade licence number')->disabled(),
                        \Filament\Forms\Components\Placeholder::make('trade_licence_file')
                            ->label('Trade licence document')
                            ->content(function (?BusinessAccount $record) {
                                if (! $record?->trade_licence_url) {
                                    return 'Not uploaded';
                                }

                                return new HtmlString(
                                    '<a href="'.e($record->trade_licence_url).'" target="_blank" class="text-primary-600 underline">View document</a>'
                                );
                            }),
                    ]),
            ]);
    }
}
