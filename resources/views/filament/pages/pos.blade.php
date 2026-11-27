<x-filament-panels::page>
    <div class="erp-pos">
        {{-- الأصناف --}}
        <x-filament::section>
            <div class="erp-filters" style="margin-bottom:.75rem">
                <div style="flex:1">
                    <input type="text" wire:model.live.debounce.300ms="search" wire:keydown.enter="scan" autofocus
                           placeholder="🔍 ابحث بالاسم أو امسح الباركود ثم Enter" style="width:100%;font-size:1rem;padding:.65rem">
                </div>
                <div>
                    <select wire:model.live="categoryId">
                        <option value="">كل التصنيفات</option>
                        @foreach($categories as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                    </select>
                </div>
            </div>
            <div class="erp-products">
                @forelse($products as $p)
                    <button type="button" class="erp-product" wire:click="addProduct({{ $p->id }})">
                        @if($p->image)<img src="{{ asset('storage/'.$p->image) }}" style="height:60px;margin:0 auto 4px;object-fit:contain">@endif
                        <div style="font-weight:600;font-size:.85rem;line-height:1.3">{{ $p->name }}</div>
                        <div class="p">{{ number_format($p->sale_price, 2) }}</div>
                        @if($p->track_stock)<div class="q {{ (float) $p->qty <= 0 ? 'text-danger' : '' }}">المتاح: {{ (float) $p->qty }}</div>@else<div class="q">خدمة</div>@endif
                    </button>
                @empty
                    <div style="opacity:.6">لا توجد أصناف مطابقة</div>
                @endforelse
            </div>
        </x-filament::section>

        {{-- السلة --}}
        <x-filament::section>
            <div class="erp-filters" style="margin-bottom:.5rem">
                <div style="flex:1"><label>العميل</label>
                    <select wire:model.live="customerId" style="width:100%">
                        <option value="">عميل نقدي (بدون حساب)</option>
                        @foreach($customers as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                    </select>
                </div>
                <div><label>المخزن</label>
                    <select wire:model.live="warehouseId" style="min-width:120px">
                        @foreach($warehouses as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                    </select>
                </div>
            </div>

            <table class="erp-table erp-cart">
                <thead><tr><th>الصنف</th><th>الكمية</th><th>السعر</th><th class="num">الإجمالي</th><th></th></tr></thead>
                <tbody>
                    @forelse($cart as $i => $line)
                        @php($l = \App\Support\DocumentTotals::line($line))
                        <tr wire:key="line-{{ $i }}">
                            <td style="font-size:.85rem">{{ $line['name'] }}</td>
                            <td><input type="number" step="any" min="0" wire:model.live.debounce.400ms="cart.{{ $i }}.quantity"></td>
                            <td><input type="number" step="any" min="0" wire:model.live.debounce.400ms="cart.{{ $i }}.unit_price"></td>
                            <td class="num">{{ number_format($l['total'] + $l['tax_amount'], 2) }}</td>
                            <td><button type="button" wire:click="removeLine({{ $i }})" class="text-danger" title="حذف">✕</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" style="text-align:center;opacity:.6;padding:2rem">السلة فارغة — اضغط على صنف لإضافته</td></tr>
                    @endforelse
                </tbody>
            </table>

            <div style="margin-top:1rem;display:flex;flex-direction:column;gap:.35rem">
                <div style="display:flex;justify-content:space-between"><span>الإجمالي قبل الضريبة</span><b>{{ number_format($totals['subtotal'], 2) }}</b></div>
                <div style="display:flex;justify-content:space-between;align-items:center"><span>خصم (مبلغ)</span>
                    <input type="number" step="any" min="0" wire:model.live.debounce.400ms="discount" style="width:110px;border:1px solid rgba(128,128,128,.35);border-radius:.4rem;padding:.25rem .4rem;background:transparent;color:inherit"></div>
                <div style="display:flex;justify-content:space-between"><span>الضريبة</span><b>{{ number_format($totals['tax_amount'], 2) }}</b></div>
                <div style="display:flex;justify-content:space-between;align-items:center;border-top:1px solid rgba(128,128,128,.25);padding-top:.5rem">
                    <span style="font-weight:700">الإجمالي</span><span class="erp-total">{{ number_format($totals['total'], 2) }} {{ $currency }}</span>
                </div>
            </div>

            <div class="erp-filters" style="margin-top:.75rem">
                <div><label>طريقة الدفع</label>
                    <select wire:model="method">
                        @foreach(\App\Models\Payment::METHODS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                    </select>
                </div>
                <div><label>الخزينة</label>
                    <select wire:model="treasuryId">
                        @foreach($treasuries as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                    </select>
                </div>
                <div><label>المبلغ المدفوع</label>
                    <input type="number" step="any" min="0" wire:model.live.debounce.400ms="paid" style="min-width:120px">
                </div>
                <div><label>&nbsp;</label><button type="button" class="erp-btn erp-btn-gray" wire:click="payExact">المبلغ كامل</button></div>
            </div>
            @if($paid > $totals['total'] && $totals['total'] > 0)
                <div class="text-success" style="font-weight:700">الباقي للعميل: {{ number_format($paid - $totals['total'], 2) }}</div>
            @elseif($paid < $totals['total'] && $totals['total'] > 0)
                <div class="text-danger" style="font-size:.85rem">المتبقي آجل على العميل: {{ number_format($totals['total'] - $paid, 2) }}</div>
            @endif

            <div style="display:flex;gap:.5rem;margin-top:1rem">
                <button type="button" class="erp-btn erp-btn-primary" style="flex:1;font-size:1.1rem;padding:.8rem" wire:click="checkout" wire:loading.attr="disabled">💳 إتمام البيع</button>
                <button type="button" class="erp-btn erp-btn-danger" wire:click="clearCart" wire:confirm="تفريغ السلة؟">تفريغ</button>
            </div>
            @if($lastInvoiceId)
                <div style="margin-top:.75rem;font-size:.85rem">
                    <a href="{{ route('print.document', ['sales-invoice', $lastInvoiceId, 'format' => 'thermal']) }}" target="_blank" style="color:rgb(79,70,229)">🖨️ إعادة طباعة آخر فاتورة</a>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
