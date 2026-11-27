@extends('print.layout')
@section('title', $title.' '.$doc->number)
@section('toolbar')
    @if($type === 'sales-invoice')
        <a href="{{ request()->fullUrlWithQuery(['format' => 'thermal']) }}">إيصال حراري 80mm</a>
    @endif
@endsection
@section('content')
@php($c = $settings['currency'])
<div class="page">
    <div class="head">
        <div>
            <h1>{{ $settings['company_name'] }}</h1>
            <div class="muted">{{ $settings['address'] }}</div>
            <div class="muted">{{ $settings['phone'] }} {{ $settings['email'] ? ' | '.$settings['email'] : '' }}</div>
            @if($settings['tax_number'])<div>الرقم الضريبي: <b>{{ $settings['tax_number'] }}</b></div>@endif
            @if($settings['commercial_register'])<div>السجل التجاري: <b>{{ $settings['commercial_register'] }}</b></div>@endif
        </div>
        <div style="text-align:left">
            @if($settings['logo'])<img src="{{ asset('storage/'.$settings['logo']) }}" style="max-height:70px">@endif
            <div style="font-weight:700;font-size:16px;direction:ltr">{{ $settings['company_name_en'] }}</div>
        </div>
    </div>

    <div class="title"><h2>{{ $title }}</h2><div class="muted" style="direction:ltr">{{ $titleEn }}</div></div>

    <div class="grid">
        <div class="box">
            <div><b>رقم المستند:</b> {{ $doc->number }}</div>
            <div><b>التاريخ:</b> {{ $doc->date->format('Y-m-d') }}</div>
            @if($doc->due_date ?? null)<div><b>تاريخ الاستحقاق:</b> {{ $doc->due_date }}</div>@endif
            @if($doc->valid_until ?? null)<div><b>صالح حتى:</b> {{ $doc->valid_until }}</div>@endif
            @if($doc->reference ?? null)<div><b>المرجع:</b> {{ $doc->reference }}</div>@endif
        </div>
        <div class="box">
            <div><b>{{ str_starts_with($type, 'purchase') ? 'المورد' : 'العميل' }}:</b> {{ $party?->name ?? 'عميل نقدي' }}</div>
            @if($party?->phone)<div><b>الهاتف:</b> {{ $party->phone }}</div>@endif
            @if($party?->tax_number)<div><b>الرقم الضريبي:</b> {{ $party->tax_number }}</div>@endif
            @if($party?->address)<div><b>العنوان:</b> {{ $party->city }} {{ $party->address }}</div>@endif
        </div>
    </div>

    <table class="items">
        <thead><tr><th>#</th><th>الصنف / الوصف</th><th>الكمية</th><th>سعر الوحدة</th><th>الخصم %</th><th>الإجمالي قبل الضريبة</th><th>الضريبة</th><th>الإجمالي</th></tr></thead>
        <tbody>
        @foreach($doc->items as $i => $item)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td class="desc">{{ $item->description ?? $item->product?->name }}</td>
                <td>{{ rtrim(rtrim(number_format($item->quantity, 3), '0'), '.') }} {{ $item->product?->unit?->short_name }}</td>
                <td>{{ number_format($item->unit_price, 2) }}</td>
                <td>{{ (float) $item->discount_percent ? number_format($item->discount_percent, 2) : '-' }}</td>
                <td>{{ number_format($item->total, 2) }}</td>
                <td>{{ number_format($item->tax_amount, 2) }} <small class="muted">({{ (float) $item->tax_rate }}%)</small></td>
                <td>{{ number_format($item->total + $item->tax_amount, 2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:20px">
        <div>
            @if($qr)<img src="{{ $qr }}" style="width:140px;height:140px">@endif
        </div>
        <table class="totals">
            <tr><td>الإجمالي قبل الخصم</td><td>{{ number_format($doc->subtotal, 2) }} {{ $c }}</td></tr>
            @if((float) $doc->discount_amount)<tr><td>الخصم</td><td>{{ number_format($doc->discount_amount, 2) }} {{ $c }}</td></tr>@endif
            <tr><td>الإجمالي الخاضع للضريبة</td><td>{{ number_format($doc->subtotal - $doc->discount_amount, 2) }} {{ $c }}</td></tr>
            <tr><td>ضريبة القيمة المضافة</td><td>{{ number_format($doc->tax_amount, 2) }} {{ $c }}</td></tr>
            @if((float) $doc->shipping)<tr><td>الشحن</td><td>{{ number_format($doc->shipping, 2) }} {{ $c }}</td></tr>@endif
            <tr class="grand"><td>الإجمالي المستحق</td><td>{{ number_format($doc->total, 2) }} {{ $c }}</td></tr>
            @isset($doc->paid_amount)
                <tr><td>المدفوع</td><td>{{ number_format($doc->paid_amount, 2) }} {{ $c }}</td></tr>
                <tr><td>المتبقي</td><td>{{ number_format($doc->remainingAmount(), 2) }} {{ $c }}</td></tr>
            @endisset
        </table>
    </div>

    <div style="margin-top:6px"><b>المبلغ كتابةً:</b> {{ \App\Support\Tafqeet::convert((float) $doc->total) }}</div>

    @if($doc->notes)<div class="box" style="margin-top:12px"><b>ملاحظات:</b> {{ $doc->notes }}</div>@endif
    @if($doc->terms)<div class="box" style="margin-top:8px"><b>الشروط والأحكام:</b> {!! nl2br(e($doc->terms)) !!}</div>@endif

    <div class="foot">
        <div class="sign">توقيع المستلم</div>
        <div class="muted">{{ $settings['invoice_footer'] }}</div>
        <div class="sign">الختم / التوقيع</div>
    </div>
</div>
@endsection
