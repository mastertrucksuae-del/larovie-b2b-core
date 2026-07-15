<?php

namespace App\Filament\Resources\Inquiries\Schemas;

use App\Models\Inquiry;
use App\Models\InquiryCharge;
use App\Support\Money;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class InquiryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                // Odoo-style status pipeline at the top.
                Section::make()
                    ->heading('Pipeline')
                    ->columnSpanFull()
                    ->schema([
                        View::make('filament.inquiry.status-pipeline'),
                        Hidden::make('status'),
                    ]),

                // Line items — full width so pricing never cramps.
                Section::make('Line items')
                    ->description('Fill in a unit price for each line. Totals update automatically.')
                    ->columnSpanFull()
                    ->schema([
                        self::itemsRepeater(),
                    ]),

                // Extra costs (shipping, handling, parking…) — billable ones hit the quote.
                self::chargesSection(),

                // Totals summary.
                Section::make()
                    ->columnSpanFull()
                    ->schema([self::totalsSummary()]),

                // Customer on the left, quote on the right.
                Grid::make(3)->columnSpanFull()->schema([
                    self::customerSection()->columnSpan(2),
                    self::quoteSection()->columnSpan(1),
                ]),

                // Attribution + pipeline timestamps (P0 #5, #6, #7).
                self::sourceSection(),
            ]);
    }

    protected static function sourceSection(): Section
    {
        return Section::make('Source & timeline')
            ->description('Where this lead came from, and how it moved through the pipeline.')
            ->columnSpanFull()
            ->collapsed()
            ->collapsible()
            ->schema([
                Placeholder::make('attribution')
                    ->hiddenLabel()
                    ->content(function ($record) {
                        if (! $record) {
                            return '—';
                        }

                        $rows = [
                            ['Received', optional($record->created_at)->format('d M Y, H:i')],
                            ['Quote sent', optional($record->quote_sent_at)->format('d M Y, H:i') ?: '—'],
                            ['Order confirmed', optional($record->order_confirmed_at)->format('d M Y, H:i') ?: '—'],
                            ['Response time', $record->responseMinutes() !== null ? $record->responseMinutes().' min' : '—'],
                            ['UTM source', $record->utm_source ?: '—'],
                            ['UTM medium', $record->utm_medium ?: '—'],
                            ['UTM campaign', $record->utm_campaign ?: '—'],
                            ['Referral code', $record->referral_code ?: '—'],
                            ['Landing page', $record->landing_page ?: '—'],
                            ['Referrer', $record->referrer ?: '—'],
                        ];

                        $html = '<div style="display:grid;grid-template-columns:auto 1fr;gap:4px 16px;font-size:13px;max-width:640px;">';
                        foreach ($rows as [$label, $value]) {
                            $html .= '<span style="color:#6b7280;">'.e($label).'</span>'
                                .'<span style="color:#111827;word-break:break-all;">'.e($value).'</span>';
                        }
                        $html .= '</div>';

                        return new \Illuminate\Support\HtmlString($html);
                    }),
            ]);
    }

    protected static function chargesSection(): Section
    {
        return Section::make('Additional charges')
            ->description('Extra costs for this order. Toggle "On quote" to bill the customer (e.g. shipping); leave off for internal costs (e.g. parking).')
            ->columnSpanFull()
            ->schema([
                Repeater::make('charges')
                    ->relationship()
                    ->hiddenLabel()
                    ->addActionLabel('Add charge')
                    ->reorderable(false)
                    ->columns(12)
                    ->defaultItems(0)
                    ->schema([
                        TextInput::make('label')
                            ->hiddenLabel()
                            ->placeholder('e.g. Shipping, Handling, Parking')
                            ->required()
                            ->columnSpan(['default' => 12, 'md' => 5]),
                        Select::make('type')
                            ->hiddenLabel()
                            ->options([
                                InquiryCharge::TYPE_FIXED => 'Fixed',
                                InquiryCharge::TYPE_PERCENT => 'Percentage',
                            ])
                            ->default(InquiryCharge::TYPE_FIXED)
                            ->selectablePlaceholder(false)
                            ->live()
                            ->columnSpan(['default' => 5, 'md' => 3]),
                        TextInput::make('amount')
                            ->hiddenLabel()
                            ->numeric()
                            ->minValue(0)
                            ->prefix(fn (Get $get) => $get('type') === InquiryCharge::TYPE_PERCENT
                                ? null
                                : (Inquiry::first()?->currency ?? 'AED'))
                            ->suffix(fn (Get $get) => $get('type') === InquiryCharge::TYPE_PERCENT ? '%' : null)
                            ->placeholder('0.00')
                            ->required()
                            ->live(onBlur: true)
                            ->columnSpan(['default' => 7, 'md' => 2]),
                        Toggle::make('is_billable')
                            ->label('On quote')
                            ->default(true)
                            ->inline(false)
                            ->columnSpan(['default' => 12, 'md' => 2]),
                    ]),
            ]);
    }

    protected static function totalsSummary(): Placeholder
    {
        return Placeholder::make('totals')
            ->hiddenLabel()
            ->content(function (Get $get) {
                $subtotal = self::sumItems($get('items'));
                $billable = self::sumBillableCharges($get('charges'), $subtotal);
                $internal = self::sumInternalCharges($get('charges'), $subtotal);
                $total = round($subtotal + $billable, 2);

                $rows = [
                    ['Products subtotal', Money::format($subtotal), false],
                ];
                if ($billable > 0) {
                    $rows[] = ['Billable charges', Money::format($billable), false];
                }
                $rows[] = ['Quote total', Money::format($total), true];
                if ($internal > 0) {
                    $rows[] = ['Internal costs (not on quote)', Money::format($internal), false];
                }

                $html = '<div style="display:flex;flex-direction:column;gap:6px;max-width:360px;margin-inline-start:auto;">';
                foreach ($rows as [$label, $value, $strong]) {
                    $style = $strong
                        ? 'font-weight:700;font-size:16px;border-top:2px solid #e5e7eb;padding-top:6px;margin-top:2px;'
                        : 'color:#6b7280;';
                    $html .= '<div style="display:flex;justify-content:space-between;gap:16px;'.$style.'">'
                        .'<span>'.e($label).'</span><span>'.e($value).'</span></div>';
                }
                $html .= '</div>';

                return new \Illuminate\Support\HtmlString($html);
            });
    }

    protected static function itemsRepeater(): Repeater
    {
        return Repeater::make('items')
            ->relationship()
            ->hiddenLabel()
            ->addable(false)
            ->deletable(false)
            ->reorderable(false)
            ->columns(3)
            ->schema([
                // Product name on its own full-width row.
                Placeholder::make('product')
                    ->hiddenLabel()
                    ->columnSpanFull()
                    ->content(function ($record) {
                        if (! $record) {
                            return '—';
                        }

                        $name = e($record->product_title)
                            .($record->display_variant_title ? ' <span class="text-gray-500">— '.e($record->display_variant_title).'</span>' : '');

                        // SKU at the end, e.g. "… - #LAR167", bold and separated.
                        $sku = $record->sku
                            ? ' - <span class="font-mono font-bold text-gray-700 dark:text-gray-200">#'.e($record->sku).'</span>'
                            : '';

                        return new \Illuminate\Support\HtmlString(
                            '<span class="text-base font-medium text-gray-950 dark:text-white">'.$name.$sku.'</span>'
                        );
                    }),
                TextInput::make('quantity')
                    ->label('Quantity')
                    ->numeric()
                    ->minValue(1)
                    ->required()
                    ->live(onBlur: true),
                TextInput::make('unit_price')
                    ->label('Unit price')
                    ->numeric()
                    ->minValue(0)
                    ->prefix(fn () => \App\Models\Setting::current()->default_currency ?? 'AED')
                    ->placeholder('0.00')
                    ->live(onBlur: true),
                Placeholder::make('line')
                    ->label('Line total')
                    ->content(function (Get $get) {
                        $price = $get('unit_price');
                        if ($price === null || $price === '') {
                            return '—';
                        }

                        return Money::format(round((int) $get('quantity') * (float) $price, 2));
                    }),
            ]);
    }

    protected static function sumItems(?array $items): float
    {
        return (float) collect($items ?? [])->sum(function ($item) {
            $price = $item['unit_price'] ?? null;
            if ($price === null || $price === '') {
                return 0;
            }

            return (int) ($item['quantity'] ?? 0) * (float) $price;
        });
    }

    /** Resolve a form-state charge row to a money value against the given base. */
    protected static function resolveCharge(array $charge, float $base): float
    {
        $amount = (float) ($charge['amount'] ?? 0);

        return ($charge['type'] ?? InquiryCharge::TYPE_FIXED) === InquiryCharge::TYPE_PERCENT
            ? round($base * $amount / 100, 2)
            : round($amount, 2);
    }

    protected static function sumBillableCharges(?array $charges, float $base): float
    {
        return (float) collect($charges ?? [])
            ->filter(fn ($c) => (bool) ($c['is_billable'] ?? true))
            ->sum(fn ($c) => self::resolveCharge($c, $base));
    }

    protected static function sumInternalCharges(?array $charges, float $base): float
    {
        return (float) collect($charges ?? [])
            ->reject(fn ($c) => (bool) ($c['is_billable'] ?? true))
            ->sum(fn ($c) => self::resolveCharge($c, $base));
    }

    protected static function customerSection(): Section
    {
        return Section::make('Customer')
            ->schema([
                TextInput::make('customer_name')->label('Name')->required(),
                Grid::make(2)->schema([
                    TextInput::make('customer_mobile')->label('Mobile')->tel(),
                    Toggle::make('is_whatsapp')->label('WhatsApp')->inline(false),
                ]),
                TextInput::make('customer_email')->label('Email')->email(),
                TextInput::make('customer_company')->label('Company'),
                Textarea::make('customer_message')->label('Customer message')->rows(2)->disabled()->dehydrated(false),
                Textarea::make('admin_notes')->label('Internal notes')->rows(2),
            ])
            ->collapsible();
    }

    protected static function quoteSection(): Section
    {
        return Section::make('Quote')
            ->schema([
                TextInput::make('quote_number')
                    ->label('Quote number')
                    ->placeholder('Auto-generated on first quote')
                    ->disabled()
                    ->dehydrated(false),
                DatePicker::make('quote_valid_until')
                    ->label('Valid until')
                    ->native(false),
                Select::make('currency')
                    ->options(['AED' => 'AED', 'USD' => 'USD', 'SAR' => 'SAR'])
                    ->default('AED'),
            ])
            ->collapsible();
    }
}
