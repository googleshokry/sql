@extends('receipts.layout')
@section('title', 'فاتورة '.$order->number)

@php($cur = config('pos.currency'))
@section('content')
    <h1>{{ config('pos.restaurant_name') }}</h1>
    @if(config('pos.restaurant_address'))<div class="center muted">{{ config('pos.restaurant_address') }}</div>@endif
    @if(config('pos.restaurant_phone'))<div class="center muted">ت: {{ config('pos.restaurant_phone') }}</div>@endif
    <hr>
    <div class="big">{{ $order->type->getLabel() }}</div>
    <table>
        <tr><td>رقم الطلب</td><td class="num"><b>{{ $order->number }}</b></td></tr>
        <tr><td>التاريخ</td><td class="num">{{ $order->created_at->format('Y-m-d h:i A') }}</td></tr>
        <tr><td>الكاشير</td><td class="num">{{ $order->user?->name }}</td></tr>
        @if($order->restaurantTable)<tr><td>الطاولة</td><td class="num"><b>{{ $order->restaurantTable->name }}</b></td></tr>@endif
    </table>

    @if($order->customer)
        <hr>
        <table>
            <tr><td>العميل</td><td class="num">{{ $order->customer->name }}</td></tr>
            <tr><td>الموبايل</td><td class="num">{{ $order->customer->phone }}</td></tr>
            @if($order->deliveryArea)<tr><td>المنطقة</td><td class="num">{{ $order->deliveryArea->name }}</td></tr>@endif
            @if($order->delivery_address)<tr><td colspan="2">العنوان: {{ $order->delivery_address }}</td></tr>@endif
            @if($order->driver)<tr><td>الطيار</td><td class="num">{{ $order->driver->name }}</td></tr>@endif
        </table>
    @endif
    <hr>
    <table>
        <thead><tr><th>الصنف</th><th>كمية</th><th>سعر</th><th class="num">إجمالي</th></tr></thead>
        <tbody>
        @foreach($order->items as $item)
            <tr>
                <td>{{ $item->product_name }}@if($item->notes)<div class="muted">* {{ $item->notes }}</div>@endif</td>
                <td>{{ $item->quantity }}</td>
                <td>{{ number_format($item->price, 2) }}</td>
                <td class="num">{{ number_format($item->total, 2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <hr>
    <table>
        <tr><td>المجموع</td><td class="num">{{ number_format($order->subtotal, 2) }} {{ $cur }}</td></tr>
        @if($order->discount > 0)<tr><td>الخصم</td><td class="num">- {{ number_format($order->discount, 2) }}</td></tr>@endif
        @if($order->service > 0)<tr><td>الخدمة</td><td class="num">{{ number_format($order->service, 2) }}</td></tr>@endif
        @if($order->tax > 0)<tr><td>الضريبة</td><td class="num">{{ number_format($order->tax, 2) }}</td></tr>@endif
        @if($order->delivery_fee > 0)<tr><td>التوصيل</td><td class="num">{{ number_format($order->delivery_fee, 2) }}</td></tr>@endif
        <tr class="total"><td>الإجمالي</td><td class="num">{{ number_format($order->total, 2) }} {{ $cur }}</td></tr>
        @if($order->is_paid)
            <tr><td>طريقة الدفع</td><td class="num">{{ $order->payment_method?->getLabel() }}</td></tr>
            <tr><td>المدفوع</td><td class="num">{{ number_format($order->paid_amount, 2) }}</td></tr>
            @if($order->change_amount > 0)<tr><td>الباقي</td><td class="num">{{ number_format($order->change_amount, 2) }}</td></tr>@endif
        @else
            <tr><td colspan="2" class="center"><b>غير مدفوع</b></td></tr>
        @endif
    </table>
    @if($order->notes)<hr><div>ملاحظات: {{ $order->notes }}</div>@endif
    <hr>
    <div class="center">{{ config('pos.receipt_footer') }}</div>
@endsection
