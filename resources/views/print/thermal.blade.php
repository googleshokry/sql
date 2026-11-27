<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8"><title>{{ $doc->number }}</title>
<style>
    body { font-family: Tahoma, 'Cairo', sans-serif; width: 76mm; margin: 0 auto; font-size: 12px; color: #000; }
    .c { text-align: center; } h3 { margin: 4px 0; }
    table { width: 100%; border-collapse: collapse; }
    td, th { padding: 2px 0; font-size: 11px; } th { border-bottom: 1px dashed #000; }
    .line { border-top: 1px dashed #000; margin: 6px 0; }
    .tot td { font-size: 13px; font-weight: bold; }
    @media print { @page { size: 80mm auto; margin: 2mm; } .np { display: none; } }
</style>
</head>
<body onload="window.print()">
<div class="c">
    <h3>{{ $settings['company_name'] }}</h3>
    <div>{{ $settings['address'] }}</div>
    <div>{{ $settings['phone'] }}</div>
    @if($settings['tax_number'])<div>{{ __('الرقم الضريبي:') }} {{ $settings['tax_number'] }}</div>@endif
    <div class="line"></div>
    <b>{{ $title }}</b>
    <div>{{ __('رقم:') }} {{ $doc->number }} — {{ $doc->created_at->format('Y-m-d H:i') }}</div>
    <div>{{ __('العميل:') }} {{ $party?->name ?? __('عميل نقدي') }}</div>
</div>
<div class="line"></div>
<table>
    <tr><th style="text-align:start">{{ __('الصنف') }}</th><th>{{ __('كمية') }}</th><th>{{ __('سعر') }}</th><th>{{ __('إجمالي') }}</th></tr>
    @foreach($doc->items as $item)
        <tr>
            <td>{{ $item->description }}</td>
            <td class="c">{{ (float) $item->quantity }}</td>
            <td class="c">{{ number_format($item->unit_price, 2) }}</td>
            <td class="c">{{ number_format($item->total + $item->tax_amount, 2) }}</td>
        </tr>
    @endforeach
</table>
<div class="line"></div>
<table>
    <tr><td>{{ __('الإجمالي قبل الضريبة') }}</td><td style="text-align:end">{{ number_format($doc->subtotal - $doc->discount_amount, 2) }}</td></tr>
    @if((float) $doc->discount_amount)<tr><td>{{ __('الخصم') }}</td><td style="text-align:end">{{ number_format($doc->discount_amount, 2) }}</td></tr>@endif
    <tr><td>{{ __('الضريبة') }}</td><td style="text-align:end">{{ number_format($doc->tax_amount, 2) }}</td></tr>
    <tr class="tot"><td>{{ __('الإجمالي') }}</td><td style="text-align:end">{{ number_format($doc->total, 2) }} {{ __($settings['currency']) }}</td></tr>
    @isset($doc->paid_amount)<tr><td>{{ __('المدفوع') }}</td><td style="text-align:end">{{ number_format($doc->paid_amount, 2) }}</td></tr>@endisset
</table>
@if($qr)<div class="c" style="margin-top:6px"><img src="{{ $qr }}" style="width:120px"></div>@endif
<div class="line"></div>
<div class="c">{{ $settings['invoice_footer'] }}</div>
<div class="c np" style="margin-top:10px"><button onclick="window.print()">{{ __('طباعة') }}</button></div>
</body>
</html>
