@extends('print.layout')
@php($s = \App\Models\Setting::allSettings())
@section('title', 'فاتورة '.$sale->invoice_number)
@section('content')
    <div class="sheet" style="width: 80mm">
        <div class="center">
            <h3 style="margin:0">{{ $s['gym_name'] }}</h3>
            <div class="muted">{{ $s['gym_address'] }} {{ $s['gym_phone'] }}</div>
            <h4 style="margin:8px 0">فاتورة بيع {{ $sale->invoice_number }}</h4>
            <div class="muted">{{ $sale->sold_at->format('Y-m-d h:i A') }} — {{ $sale->member?->name ?? 'عميل نقدي' }}</div>
        </div>
        <table style="margin-top:8px">
            <tr><th>الصنف</th><th>الكمية</th><th>السعر</th><th>الإجمالي</th></tr>
            @foreach ($sale->items as $i)
                <tr><td>{{ $i->product?->name }}</td><td>{{ $i->quantity }}</td><td>{{ number_format($i->unit_price, 2) }}</td><td>{{ number_format($i->total, 2) }}</td></tr>
            @endforeach
            <tr><td colspan="3">المجموع</td><td>{{ number_format($sale->subtotal, 2) }}</td></tr>
            @if ($sale->discount > 0)<tr><td colspan="3">الخصم</td><td>{{ number_format($sale->discount, 2) }}</td></tr>@endif
            <tr><td colspan="3"><b>الإجمالي</b></td><td><b>{{ \App\Support\Money::format($sale->total) }}</b></td></tr>
        </table>
        <p class="center muted">{{ $s['receipt_footer'] }}</p>
    </div>
@endsection
