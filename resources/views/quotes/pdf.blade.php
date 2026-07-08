@php
    $isPurchaseOrder = $isPurchaseOrder ?? false;
    $images = $images ?? [];
    $rtl = $inquiry->locale === 'ar';
    $ar = fn ($en, $arText) => $rtl ? $arText : $en;
    $brand = $settings->brand_color ?: '#B76E79';
    $currency = $inquiry->currency ?: 'AED';
    $fmt = fn ($n) => $n === null ? '—' : $currency.' '.number_format((float) $n, 2);
    $hasArabicFont = file_exists(storage_path('fonts/Cairo-Regular.ttf'));
    $bodyFont = ($rtl && $hasArabicFont) ? "'Cairo', 'DejaVu Sans'" : "'DejaVu Sans'";
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @font-face {
            font-family: 'Cairo';
            font-style: normal;
            font-weight: 400;
            src: url("{{ storage_path('fonts/Cairo-Regular.ttf') }}") format('truetype');
        }
        @font-face {
            font-family: 'Cairo';
            font-style: normal;
            font-weight: 700;
            src: url("{{ storage_path('fonts/Cairo-Bold.ttf') }}") format('truetype');
        }
        * { box-sizing: border-box; }
        body {
            font-family: {{ $bodyFont }}, sans-serif;
            color: #1f2937;
            font-size: 12px;
            margin: 0;
            padding: 0;
            direction: {{ $rtl ? 'rtl' : 'ltr' }};
        }
        .wrap { padding: 32px 36px; }
        .header { border-bottom: 3px solid {{ $brand }}; padding-bottom: 16px; margin-bottom: 24px; }
        .header table { width: 100%; }
        .brand { font-size: 26px; font-weight: 700; color: {{ $brand }}; }
        .doc-title { font-size: 20px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #374151; }
        .muted { color: #6b7280; }
        .meta td { padding: 2px 0; vertical-align: top; }
        .section-title { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: {{ $brand }}; font-weight: 700; margin-bottom: 4px; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.items th {
            background: {{ $brand }}; color: #fff; padding: 8px 10px; font-size: 11px;
            text-align: {{ $rtl ? 'right' : 'left' }};
        }
        table.items th.num, table.items td.num { text-align: {{ $rtl ? 'left' : 'right' }}; }
        table.items td { padding: 8px 10px; border-bottom: 1px solid #e5e7eb; }
        .totals { width: 42%; margin-top: 16px; {{ $rtl ? 'float:left;' : 'float:right;' }} }
        .totals td { padding: 5px 10px; }
        .totals .grand { font-size: 14px; font-weight: 700; border-top: 2px solid {{ $brand }}; }
        .terms { clear: both; margin-top: 48px; font-size: 11px; color: #4b5563; }
        .footer { margin-top: 24px; padding-top: 12px; border-top: 1px solid #e5e7eb; text-align: center; color: #9ca3af; font-size: 10px; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="header">
        <table>
            <tr>
                <td style="width:60%;">
                    <div class="brand">{{ $settings->company_name }}</div>
                    <div class="muted" style="margin-top:4px;">{{ $settings->company_address }}</div>
                    <div class="muted">{{ $settings->company_email }} · {{ $settings->company_phone }}</div>
                    @if ($settings->trn)
                        <div class="muted">{{ $ar('TRN', 'الرقم الضريبي') }}: {{ $settings->trn }}</div>
                    @endif
                </td>
                <td style="width:40%; text-align:{{ $rtl ? 'left' : 'right' }};">
                    <div class="doc-title">{{ $isPurchaseOrder ? $ar('Purchase Order', 'أمر شراء') : $ar('Quotation', 'عرض سعر') }}</div>
                    <table class="meta" style="margin-top:8px; width:100%;">
                        <tr>
                            <td class="muted">{{ $isPurchaseOrder ? $ar('PO No.', 'رقم الأمر') : $ar('Quote No.', 'رقم العرض') }}</td>
                            <td style="text-align:{{ $rtl ? 'left' : 'right' }};"><strong>{{ $inquiry->quote_number }}</strong></td>
                        </tr>
                        <tr>
                            <td class="muted">{{ $ar('Reference', 'المرجع') }}</td>
                            <td style="text-align:{{ $rtl ? 'left' : 'right' }};">{{ $inquiry->reference }}</td>
                        </tr>
                        <tr>
                            <td class="muted">{{ $ar('Date', 'التاريخ') }}</td>
                            <td style="text-align:{{ $rtl ? 'left' : 'right' }};">{{ now()->format('d M Y') }}</td>
                        </tr>
                        <tr>
                            <td class="muted">{{ $ar('Valid until', 'صالح حتى') }}</td>
                            <td style="text-align:{{ $rtl ? 'left' : 'right' }};">{{ optional($inquiry->quote_valid_until)->format('d M Y') }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>

    @unless ($isPurchaseOrder)
        <div class="section-title">{{ $ar('Prepared for', 'مُعدّ لـ') }}</div>
        <div style="margin-bottom:16px;">
            <strong>{{ $inquiry->customer_name }}</strong><br>
            @if ($inquiry->customer_company){{ $inquiry->customer_company }}<br>@endif
            <span class="muted">{{ $inquiry->customer_mobile }}</span>
            @if ($inquiry->customer_email)<span class="muted"> · {{ $inquiry->customer_email }}</span>@endif
        </div>
    @endunless

    <table class="items">
        <thead>
            <tr>
                <th style="width:52px;">{{ $ar('Image', 'الصورة') }}</th>
                <th>{{ $ar('Product', 'المنتج') }}</th>
                <th>{{ $ar('SKU', 'الرمز') }}</th>
                <th class="num">{{ $ar('Qty', 'الكمية') }}</th>
                <th class="num">{{ $ar('Unit price', 'سعر الوحدة') }}</th>
                <th class="num">{{ $ar('Line total', 'الإجمالي') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($inquiry->items as $item)
                <tr>
                    <td style="width:52px; text-align:center;">
                        @if (! empty($images[$item->id]))
                            <img src="{{ $images[$item->id] }}" style="width:40px; height:40px; object-fit:cover; border-radius:4px;" alt="">
                        @endif
                    </td>
                    <td>
                        {{ $item->product_title }}
                        @if ($item->variant_title)<br><span class="muted">{{ $item->variant_title }}</span>@endif
                    </td>
                    <td class="muted">{{ $item->sku }}</td>
                    <td class="num">{{ $item->quantity }}</td>
                    <td class="num">{{ $fmt($item->unit_price) }}</td>
                    <td class="num">{{ $fmt($item->line_total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="muted">{{ $ar('Subtotal', 'المجموع الفرعي') }}</td>
            <td style="text-align:{{ $rtl ? 'left' : 'right' }};">{{ $fmt($inquiry->quoted_subtotal) }}</td>
        </tr>
        @foreach ($inquiry->charges->where('is_billable', true) as $charge)
            <tr>
                <td class="muted">{{ $charge->displayLabel() }}</td>
                <td style="text-align:{{ $rtl ? 'left' : 'right' }};">{{ $fmt($charge->resolve((float) $inquiry->quoted_subtotal)) }}</td>
            </tr>
        @endforeach
        <tr class="grand">
            <td>{{ $ar('Total', 'الإجمالي') }}</td>
            <td style="text-align:{{ $rtl ? 'left' : 'right' }};">{{ $fmt($inquiry->quoted_total) }}</td>
        </tr>
    </table>

    @if ($settings->quote_terms)
        <div class="terms">
            <div class="section-title">{{ $ar('Terms', 'الشروط') }}</div>
            {!! nl2br(e($settings->quote_terms)) !!}
        </div>
    @endif

    <div class="footer">
        {{ $settings->quote_footer_note }}
    </div>
</div>
</body>
</html>
