@extends('receipts.layout')
@section('title', 'مطبخ '.$order->number)

@section('content')
    <h2>طلب مطبخ</h2>
    <div class="big">{{ $order->type->getLabel() }} @if($order->restaurantTable) - {{ $order->restaurantTable->name }} @endif</div>
    <div class="center">#{{ $order->number }} | {{ $order->created_at->format('h:i A') }}</div>
    <hr>
    <table>
        <thead><tr><th>الكمية</th><th>الصنف</th></tr></thead>
        <tbody>
        @foreach($order->items as $item)
            <tr style="font-size:15px">
                <td><b>{{ $item->quantity }} ×</b></td>
                <td><b>{{ $item->product_name }}</b>@if($item->notes)<div>** {{ $item->notes }}</div>@endif</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @if($order->notes)<hr><div><b>ملاحظات:</b> {{ $order->notes }}</div>@endif
@endsection
