@extends('print.layout')
@section('title', $payment->number)
@section('content')
@php($in = $payment->direction === 'in')
<div class="page" style="min-height:auto">
    <div class="head">
        <div>
            <h1>{{ $settings['company_name'] }}</h1>
            <div class="muted">{{ $settings['address'] }} {{ $settings['phone'] }}</div>
            @if($settings['tax_number'])<div>الرقم الضريبي: {{ $settings['tax_number'] }}</div>@endif
        </div>
        <div style="text-align:left">@if($settings['logo'])<img src="{{ asset('storage/'.$settings['logo']) }}" style="max-height:70px">@endif</div>
    </div>
    <div class="title"><h2>{{ $in ? 'سند قبض' : 'سند صرف' }}</h2><div class="muted" style="direction:ltr">{{ $in ? 'Receipt Voucher' : 'Payment Voucher' }}</div></div>
    <div class="grid">
        <div class="box"><b>رقم السند:</b> {{ $payment->number }}<br><b>التاريخ:</b> {{ $payment->date->format('Y-m-d') }}</div>
        <div class="box" style="font-size:18px;text-align:center"><b>المبلغ:</b> {{ number_format($payment->amount, 2) }} {{ $settings['currency'] }}</div>
    </div>
    <div class="box" style="line-height:2.2">
        <div><b>{{ $in ? 'استلمنا من' : 'صرفنا إلى' }}:</b> {{ $payment->party?->name ?? $payment->account?->name ?? '-' }}</div>
        <div><b>مبلغ وقدره:</b> {{ \App\Support\Tafqeet::convert((float) $payment->amount) }}</div>
        <div><b>طريقة الدفع:</b> {{ \App\Models\Payment::METHODS[$payment->method] ?? $payment->method }} {{ $payment->reference ? '— مرجع: '.$payment->reference : '' }}</div>
        <div><b>الخزينة:</b> {{ $payment->treasury->name }}</div>
        @if($payment->payable)<div><b>عن فاتورة:</b> {{ $payment->payable->number }}</div>@endif
        <div><b>البيان:</b> {{ $payment->notes }}</div>
    </div>
    <div class="foot"><div class="sign">المستلم</div><div class="sign">المحاسب</div><div class="sign">المدير</div></div>
</div>
@endsection
