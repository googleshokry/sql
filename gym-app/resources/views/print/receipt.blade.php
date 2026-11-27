@extends('print.layout')
@php($s = \App\Models\Setting::allSettings())
@section('title', __('إيصال :number', ['number' => $payment->receipt_number]))
@section('content')
    <div class="sheet" style="width: 80mm">
        <div class="center">
            <h3 style="margin:0">{{ $s['gym_name'] }}</h3>
            <div class="muted">{{ $s['gym_address'] }} {{ $s['gym_phone'] }}</div>
            <h4 style="margin:8px 0">{{ __('إيصال استلام نقدية') }}</h4>
        </div>
        <table>
            <tr><td>{{ __('رقم الإيصال') }}</td><td><b>{{ $payment->receipt_number }}</b></td></tr>
            <tr><td>{{ __('التاريخ') }}</td><td>{{ $payment->paid_at->format('Y-m-d h:i A') }}</td></tr>
            <tr><td>{{ __('المشترك') }}</td><td>{{ $payment->member?->name ?? '-' }} ({{ $payment->member?->code }})</td></tr>
            <tr><td>{{ __('البيان') }}</td><td>{{ \App\Models\Payment::types()[$payment->type] ?? $payment->type }} {{ $payment->subscription?->plan?->name }}</td></tr>
            @if ($payment->subscription)
                <tr><td>{{ __('مدة الاشتراك') }}</td><td>{{ $payment->subscription->start_date->format('Y-m-d') }} ← {{ $payment->subscription->end_date->format('Y-m-d') }}</td></tr>
                <tr><td>{{ __('إجمالي الاشتراك') }}</td><td>{{ \App\Support\Money::format($payment->subscription->total) }}</td></tr>
            @endif
            <tr><td>{{ __('طريقة الدفع') }}</td><td>{{ \App\Models\Payment::methods()[$payment->method] ?? $payment->method }}</td></tr>
            <tr><td><b>{{ __('المبلغ المدفوع') }}</b></td><td><b style="font-size:16px">{{ \App\Support\Money::format($payment->amount) }}</b></td></tr>
            @if ($payment->subscription)
                <tr><td>{{ __('المتبقي') }}</td><td>{{ \App\Support\Money::format($payment->subscription->remaining_amount) }}</td></tr>
            @endif
            <tr><td>{{ __('الموظف') }}</td><td>{{ $payment->receiver?->name }}</td></tr>
        </table>
        @if ($payment->notes)<p class="muted">{{ $payment->notes }}</p>@endif
        <p class="center muted">{{ $s['receipt_footer'] }}</p>
    </div>
@endsection
