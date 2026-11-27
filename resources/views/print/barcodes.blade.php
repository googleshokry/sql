@extends('print.layout')
@section('title', 'طباعة باركود')
@section('styles')
    .labels { display: flex; flex-wrap: wrap; gap: 4mm; padding: 6mm; background:#fff; width: 210mm; margin: auto; }
    .label { width: 48mm; height: 28mm; border: 1px dashed #ccc; text-align: center; padding: 1mm; overflow: hidden; }
    .label .n { font-size: 10px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .label .p { font-size: 11px; font-weight: 800; }
    .label svg { width: 100%; height: 14mm; }
    @media print { .label { border: 0; } .labels { width: auto; } }
@endsection
@section('toolbar')
    <form style="display:inline"><input type="hidden" name="ids" value="{{ request('ids') }}"> النسخ لكل صنف: <input name="copies" type="number" value="{{ $copies }}" style="width:60px"> <button>تحديث</button></form>
@endsection
@section('content')
<div class="labels">
    @foreach($products as $product)
        @for($i = 0; $i < $copies; $i++)
            <div class="label">
                <div class="n">{{ $product->name }}</div>
                <svg class="bc" data-code="{{ $product->barcode ?: $product->sku }}"></svg>
                <div class="p">{{ number_format($product->sale_price, 2) }} {{ \App\Models\Setting::get('currency') }}</div>
            </div>
        @endfor
    @endforeach
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jsbarcode/3.11.6/JsBarcode.all.min.js"></script>
<script>document.querySelectorAll('.bc').forEach(el => JsBarcode(el, el.dataset.code, { height: 40, fontSize: 12, margin: 0, width: 1.6 }));</script>
@endsection
