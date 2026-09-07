<?php

namespace App\Filament\Resources\BusinessAccounts\Schemas;

use App\Models\BusinessAccount;
use App\Models\Inquiry;
use App\Support\AccountInsights;
use App\Support\Money;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * The read-only account view: who they are, where their application stands, and
 * what they are worth.
 *
 * The value figures come from `AccountInsights`, which counts a customer's guest
 * inquiries alongside the ones submitted while signed in — otherwise a long
 * standing buyer who only recently registered would show as brand new.
 */
class BusinessAccountInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            self::status(),
            self::applicant(),
            self::verification(),
            self::value(),
            self::pipeline(),
            self::topProducts(),
            self::recentInquiries(),
        ]);
    }

    private static function status(): Section
    {
        return Section::make('Application status')
            ->columns(4)
            ->schema([
                TextEntry::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => BusinessAccount::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        BusinessAccount::STATUS_PENDING => 'warning',
                        BusinessAccount::STATUS_APPROVED => 'success',
                        BusinessAccount::STATUS_REJECTED => 'danger',
                        default => 'gray',
                    }),
                TextEntry::make('created_at')
                    ->label('Registered')
                    ->dateTime('d M Y, H:i')
                    ->since(),
                TextEntry::make('approved_at')
                    ->label('Approved')
                    ->dateTime('d M Y, H:i')
                    ->placeholder('Not yet approved'),
                TextEntry::make('reviewer.name')
                    ->label('Reviewed by')
                    ->placeholder('—'),
                TextEntry::make('review_notes')
                    ->label('Review notes')
                    ->columnSpanFull()
                    ->visible(fn (BusinessAccount $record) => filled($record->review_notes)),
            ]);
    }

    private static function applicant(): Section
    {
        return Section::make('Applicant')
            ->columns(3)
            ->schema([
                TextEntry::make('company_name')->label('Company')->weight('bold'),
                TextEntry::make('contact_person')->label('Contact person'),
                TextEntry::make('locale')
                    ->label('Preferred language')
                    ->formatStateUsing(fn (?string $state) => $state === 'ar' ? 'Arabic' : 'English'),
                TextEntry::make('email')
                    ->label('Email')
                    ->icon('heroicon-o-envelope')
                    ->copyable()
                    ->url(fn (BusinessAccount $record) => 'mailto:'.$record->email),
                TextEntry::make('phone')
                    ->label('Phone')
                    ->icon('heroicon-o-phone')
                    ->copyable()
                    ->url(fn (BusinessAccount $record) => 'tel:'.$record->phone),
            ]);
    }

    private static function verification(): Section
    {
        return Section::make('Verification')
            ->columns(2)
            ->schema([
                TextEntry::make('trade_licence_number')
                    ->label('Trade licence number')
                    ->placeholder('Not provided'),
                TextEntry::make('trade_licence_path')
                    ->label('Trade licence document')
                    ->placeholder('No document uploaded')
                    ->formatStateUsing(fn () => 'Download document')
                    ->icon('heroicon-o-document-arrow-down')
                    ->url(fn (BusinessAccount $record) => $record->trade_licence_url)
                    ->openUrlInNewTab(),
            ]);
    }

    private static function value(): Section
    {
        return Section::make('Activity & value')
            ->description('Counts this customer\'s guest inquiries as well as those submitted while signed in.')
            ->columns(4)
            ->schema([
                TextEntry::make('insight_total')
                    ->label('Inquiries')
                    ->state(fn (BusinessAccount $r) => AccountInsights::for($r)->summary()['total'])
                    ->weight('bold'),
                TextEntry::make('insight_confirmed')
                    ->label('Confirmed orders')
                    ->state(fn (BusinessAccount $r) => AccountInsights::for($r)->summary()['confirmed'])
                    ->weight('bold'),
                TextEntry::make('insight_conversion')
                    ->label('Conversion')
                    ->state(function (BusinessAccount $r) {
                        $rate = AccountInsights::for($r)->summary()['conversion'];

                        return $rate === null ? '—' : $rate.'%';
                    }),
                TextEntry::make('insight_open')
                    ->label('Open inquiries')
                    ->state(fn (BusinessAccount $r) => AccountInsights::for($r)->summary()['open']),
                TextEntry::make('insight_confirmed_value')
                    ->label('Confirmed value')
                    ->state(fn (BusinessAccount $r) => Money::format(AccountInsights::for($r)->summary()['confirmed_value']))
                    ->weight('bold')
                    ->color('success'),
                TextEntry::make('insight_quoted_value')
                    ->label('Quoted value')
                    ->helperText('All inquiries, whether or not they closed.')
                    ->state(fn (BusinessAccount $r) => Money::format(AccountInsights::for($r)->summary()['quoted_value'])),
                TextEntry::make('insight_average')
                    ->label('Average order')
                    ->state(function (BusinessAccount $r) {
                        $average = AccountInsights::for($r)->summary()['average_order'];

                        return $average === null ? '—' : Money::format($average);
                    }),
                TextEntry::make('insight_last')
                    ->label('Last inquiry')
                    ->state(function (BusinessAccount $r) {
                        $last = AccountInsights::for($r)->summary()['last_at'];

                        return $last ? $last->format('d M Y').' ('.$last->diffForHumans().')' : 'Never';
                    }),
            ]);
    }

    private static function pipeline(): Section
    {
        return Section::make('Where their inquiries sit')
            ->visible(fn (BusinessAccount $r) => AccountInsights::for($r)->pipeline()->isNotEmpty())
            ->schema([
                RepeatableEntry::make('insight_pipeline')
                    ->hiddenLabel()
                    ->columns(2)
                    ->state(fn (BusinessAccount $r) => AccountInsights::for($r)->pipeline()
                        ->map(fn (int $total, string $status) => [
                            'stage' => Inquiry::STATUSES[$status] ?? $status,
                            'total' => $total,
                        ])
                        ->values()
                        ->all())
                    ->schema([
                        TextEntry::make('stage')->hiddenLabel(),
                        TextEntry::make('total')->hiddenLabel()->badge(),
                    ]),
            ]);
    }

    private static function topProducts(): Section
    {
        return Section::make('Most requested products')
            ->visible(fn (BusinessAccount $r) => AccountInsights::for($r)->topProducts()->isNotEmpty())
            ->schema([
                RepeatableEntry::make('insight_products')
                    ->hiddenLabel()
                    ->columns(3)
                    ->state(fn (BusinessAccount $r) => AccountInsights::for($r)->topProducts()
                        ->map(fn ($p) => [
                            'title' => $p->title,
                            'quantity' => $p->quantity.' units',
                            'orders' => $p->orders.' '.str('inquiry')->plural($p->orders),
                        ])
                        ->all())
                    ->schema([
                        TextEntry::make('title')->hiddenLabel()->columnSpan(1),
                        TextEntry::make('quantity')->hiddenLabel()->badge(),
                        TextEntry::make('orders')->hiddenLabel()->color('gray'),
                    ]),
            ]);
    }

    private static function recentInquiries(): Section
    {
        return Section::make('Recent inquiries')
            ->visible(fn (BusinessAccount $r) => AccountInsights::for($r)->summary()['total'] > 0)
            ->schema([
                RepeatableEntry::make('insight_recent')
                    ->hiddenLabel()
                    ->columns(5)
                    ->state(fn (BusinessAccount $r) => $r->matchedInquiries()
                        ->latest('created_at')
                        ->limit(10)
                        ->get()
                        ->map(fn (Inquiry $i) => [
                            'reference' => $i->reference,
                            'status' => $i->statusLabel(),
                            'total' => $i->quoted_total === null ? 'Not quoted' : Money::format($i->quoted_total, $i->currency),
                            'date' => $i->created_at?->format('d M Y'),
                            'url' => \App\Filament\Resources\Inquiries\InquiryResource::getUrl('edit', ['record' => $i]),
                        ])
                        ->all())
                    ->schema([
                        TextEntry::make('reference')->hiddenLabel()->weight('bold'),
                        TextEntry::make('status')->hiddenLabel()->badge(),
                        TextEntry::make('total')->hiddenLabel(),
                        TextEntry::make('date')->hiddenLabel()->color('gray'),
                        // A child entry inside a RepeatableEntry is handed its own
                        // value, not the whole row, so the link lives in an entry
                        // whose state is the URL itself.
                        TextEntry::make('url')
                            ->hiddenLabel()
                            ->formatStateUsing(fn () => 'Open')
                            ->icon('heroicon-o-arrow-top-right-on-square')
                            ->url(fn (string $state) => $state),
                    ]),
            ]);
    }
}
