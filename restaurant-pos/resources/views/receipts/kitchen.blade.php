@extends('receipts.layout')
@section('title', __('مطبخ').' '.$order->number)

@section('content')
    <h2>{{ __('طلب مطبخ') }}</h2>
    <div class="big">{{ $order->type->getLabel() }} @if($order->restaurantTable) - {{ $order->restaurantTable->name }} @endif</div>
    <div class="center">#{{ $order->number }} | {{ $order->created_at->format('h:i A') }}</div>
    <hr>
    <table>
        <thead><tr><th>{{ __('الكمية') }}</th><th>{{ __('الصنف') }}</th></tr></thead>
        <tbody>
        @foreach($order->items as $item)
            <tr style="font-size:15px">
                <td><b>{{ $item->quantity }} ×</b></td>
                <td><b>{{ $item->product_name }}</b>@if($item->notes)<div>** {{ $item->notes }}</div>@endif</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @if($order->notes)<hr><div><b>{{ __('ملاحظات:') }}</b> {{ $order->notes }}</div>@endif
@endsection
