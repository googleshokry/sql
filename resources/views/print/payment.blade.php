@extends('print.layout')
@section('title', $payment->number)
@section('content')
@php($in = $payment->direction === 'in')
<div class="page" style="min-height:auto">
    <div class="head">
        <div>
            <h1>{{ $settings['company_name'] }}</h1>
            <div class="muted">{{ $settings['address'] }} {{ $settings['phone'] }}</div>
            @if($settings['tax_number'])<div>{{ __('الرقم الضريبي:') }} {{ $settings['tax_number'] }}</div>@endif
        </div>
        <div style="text-align:end">@if($settings['logo'])<img src="{{ asset('storage/'.$settings['logo']) }}" style="max-height:70px">@endif</div>
    </div>
    <div class="title"><h2>{{ $in ? __('سند قبض') : __('سند صرف') }}</h2>@if(app()->getLocale() === 'ar')<div class="muted" style="direction:ltr">{{ $in ? 'Receipt Voucher' : 'Payment Voucher' }}</div>@endif</div>
    <div class="grid">
        <div class="box"><b>{{ __('رقم السند:') }}</b> {{ $payment->number }}<br><b>{{ __('التاريخ:') }}</b> {{ $payment->date->format('Y-m-d') }}</div>
        <div class="box" style="font-size:18px;text-align:center"><b>{{ __('المبلغ:') }}</b> {{ number_format($payment->amount, 2) }} {{ __($settings['currency']) }}</div>
    </div>
    <div class="box" style="line-height:2.2">
        <div><b>{{ $in ? __('استلمنا من') : __('صرفنا إلى') }}:</b> {{ $payment->party?->name ?? $payment->account?->name ?? '-' }}</div>
        <div><b>{{ __('مبلغ وقدره:') }}</b> {{ \App\Support\Tafqeet::convert((float) $payment->amount) }}</div>
        <div><b>{{ __('طريقة الدفع:') }}</b> {{ \App\Models\Payment::METHODS[$payment->method] ?? $payment->method }} {{ $payment->reference ? __('— مرجع: ').$payment->reference : '' }}</div>
        <div><b>{{ __('الخزينة:') }}</b> {{ $payment->treasury->name }}</div>
        @if($payment->payable)<div><b>{{ __('عن فاتورة:') }}</b> {{ $payment->payable->number }}</div>@endif
        <div><b>{{ __('البيان:') }}</b> {{ $payment->notes }}</div>
    </div>
    <div class="foot"><div class="sign">{{ __('المستلم') }}</div><div class="sign">{{ __('المحاسب') }}</div><div class="sign">{{ __('المدير') }}</div></div>
</div>
@endsection
