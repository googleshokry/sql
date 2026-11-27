<!DOCTYPE html>
<html lang="ar" dir="rtl">
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
    @if($settings['tax_number'])<div>الرقم الضريبي: {{ $settings['tax_number'] }}</div>@endif
    <div class="line"></div>
    <b>{{ $title }}</b>
    <div>رقم: {{ $doc->number }} — {{ $doc->created_at->format('Y-m-d H:i') }}</div>
    <div>العميل: {{ $party?->name ?? 'عميل نقدي' }}</div>
</div>
<div class="line"></div>
<table>
    <tr><th style="text-align:right">الصنف</th><th>كمية</th><th>سعر</th><th>إجمالي</th></tr>
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
    <tr><td>الإجمالي قبل الضريبة</td><td style="text-align:left">{{ number_format($doc->subtotal - $doc->discount_amount, 2) }}</td></tr>
    @if((float) $doc->discount_amount)<tr><td>الخصم</td><td style="text-align:left">{{ number_format($doc->discount_amount, 2) }}</td></tr>@endif
    <tr><td>الضريبة</td><td style="text-align:left">{{ number_format($doc->tax_amount, 2) }}</td></tr>
    <tr class="tot"><td>الإجمالي</td><td style="text-align:left">{{ number_format($doc->total, 2) }} {{ $settings['currency'] }}</td></tr>
    @isset($doc->paid_amount)<tr><td>المدفوع</td><td style="text-align:left">{{ number_format($doc->paid_amount, 2) }}</td></tr>@endisset
</table>
@if($qr)<div class="c" style="margin-top:6px"><img src="{{ $qr }}" style="width:120px"></div>@endif
<div class="line"></div>
<div class="c">{{ $settings['invoice_footer'] }}</div>
<div class="c np" style="margin-top:10px"><button onclick="window.print()">طباعة</button></div>
</body>
</html>
