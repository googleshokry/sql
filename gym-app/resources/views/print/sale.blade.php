@extends('print.layout')
@php($s = \App\Models\Setting::allSettings())
@section('title', __('فاتورة :number', ['number' => $sale->invoice_number]))
@section('content')
    <div class="sheet" style="width: 80mm">
        <div class="center">
            <h3 style="margin:0">{{ $s['gym_name'] }}</h3>
            <div class="muted">{{ $s['gym_address'] }} {{ $s['gym_phone'] }}</div>
            <h4 style="margin:8px 0">{{ __('فاتورة بيع') }} {{ $sale->invoice_number }}</h4>
            <div class="muted">{{ $sale->sold_at->format('Y-m-d h:i A') }} — {{ $sale->member?->name ?? __('عميل نقدي') }}</div>
        </div>
        <table style="margin-top:8px">
            <tr><th>{{ __('الصنف') }}</th><th>{{ __('الكمية') }}</th><th>{{ __('السعر') }}</th><th>{{ __('الإجمالي') }}</th></tr>
            @foreach ($sale->items as $i)
                <tr><td>{{ $i->product?->name }}</td><td>{{ $i->quantity }}</td><td>{{ number_format($i->unit_price, 2) }}</td><td>{{ number_format($i->total, 2) }}</td></tr>
            @endforeach
            <tr><td colspan="3">{{ __('المجموع') }}</td><td>{{ number_format($sale->subtotal, 2) }}</td></tr>
            @if ($sale->discount > 0)<tr><td colspan="3">{{ __('الخصم') }}</td><td>{{ number_format($sale->discount, 2) }}</td></tr>@endif
            <tr><td colspan="3"><b>{{ __('الإجمالي') }}</b></td><td><b>{{ \App\Support\Money::format($sale->total) }}</b></td></tr>
        </table>
        <p class="center muted">{{ $s['receipt_footer'] }}</p>
    </div>
@endsection
