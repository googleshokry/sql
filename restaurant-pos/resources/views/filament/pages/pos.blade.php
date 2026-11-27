<x-filament-panels::page>
    @php
        $t = $this->totals;
        $cur = config('pos.currency');
        $open = $this->openTableOrder;
    @endphp

    <style>
        .pos { display: grid; grid-template-columns: 1fr 420px; gap: 16px; align-items: start; }
        @media (max-width: 1100px) { .pos { grid-template-columns: 1fr; } }
        .pos-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 12px; }
        .dark .pos-card { background: #18181b; border-color: #3f3f46; }
        .pos-row { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
        .pos-input { width: 100%; padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 8px; background: transparent; color: inherit; font-size: 14px; }
        .dark .pos-input { border-color: #52525b; }
        .dark .pos-input option { background: #18181b; }
        .pos-chip { padding: 8px 14px; border-radius: 999px; border: 1px solid #d1d5db; cursor: pointer; font-size: 14px; background: transparent; color: inherit; white-space: nowrap; }
        .pos-chip.active { background: #f97316; color: #fff; border-color: #f97316; }
        .pos-cats { overflow-x: auto; flex-wrap: nowrap; padding-bottom: 4px; }
        .pos-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 10px; margin-top: 12px; max-height: calc(100vh - 260px); overflow-y: auto; }
        .pos-product { border: 1px solid #e5e7eb; border-radius: 12px; padding: 8px; text-align: center; cursor: pointer; background: transparent; color: inherit; transition: .1s; }
        .dark .pos-product { border-color: #3f3f46; }
        .pos-product:hover { border-color: #f97316; transform: translateY(-1px); }
        .pos-product:active { transform: scale(.97); }
        .pos-product img, .pos-product .ph { width: 100%; height: 80px; object-fit: cover; border-radius: 8px; background: #fff7ed; display: flex; align-items: center; justify-content: center; font-size: 30px; }
        .dark .pos-product .ph { background: #27272a; }
        .pos-product .n { font-weight: 600; margin-top: 6px; font-size: 14px; }
        .pos-product .p { color: #f97316; font-weight: 700; }
        .pos-types { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; }
        .pos-types button { padding: 10px; border-radius: 10px; border: 1px solid #d1d5db; font-weight: 700; cursor: pointer; background: transparent; color: inherit; }
        .pos-types button.active { background: #f97316; border-color: #f97316; color: #fff; }
        .pos-tables { display: grid; grid-template-columns: repeat(5, 1fr); gap: 6px; margin-top: 8px; }
        .pos-table { padding: 8px 2px; border-radius: 8px; border: 2px solid #22c55e; cursor: pointer; font-size: 13px; background: transparent; color: inherit; }
        .pos-table.busy { border-color: #ef4444; background: rgba(239, 68, 68, .1); }
        .pos-table.sel { background: #f97316; color: #fff; border-color: #f97316; }
        .pos-cart { max-height: 300px; overflow-y: auto; margin: 10px 0; }
        .pos-item { border-bottom: 1px dashed #e5e7eb; padding: 6px 0; }
        .dark .pos-item { border-color: #3f3f46; }
        .pos-item .top { display: flex; justify-content: space-between; gap: 6px; align-items: center; }
        .pos-qty { display: flex; align-items: center; gap: 4px; }
        .pos-qty button { width: 28px; height: 28px; border-radius: 6px; border: 1px solid #d1d5db; cursor: pointer; background: transparent; color: inherit; font-weight: 700; }
        .pos-qty .del { color: #ef4444; border-color: #fca5a5; }
        .pos-note { font-size: 12px; padding: 3px 8px; margin-top: 4px; }
        .pos-sum div { display: flex; justify-content: space-between; padding: 2px 0; font-size: 14px; }
        .pos-sum .grand { font-size: 20px; font-weight: 800; color: #f97316; border-top: 1px solid #e5e7eb; margin-top: 4px; padding-top: 6px; }
        .pos-btn { width: 100%; padding: 12px; border-radius: 10px; border: 0; font-weight: 800; font-size: 16px; cursor: pointer; color: #fff; }
        .pos-btn.pay { background: #16a34a; }
        .pos-btn.hold { background: #2563eb; }
        .pos-btn.clear { background: #6b7280; font-size: 13px; padding: 8px; }
        .pos-btn:disabled { opacity: .5; cursor: not-allowed; }
        .pos-label { font-size: 12px; color: #6b7280; margin: 8px 0 3px; display: block; }
        .pos-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .pos-alert { background: #fef3c7; color: #92400e; border-radius: 8px; padding: 6px 10px; font-size: 13px; margin-top: 8px; }
        .pos-empty { text-align: center; color: #9ca3af; padding: 30px 0; }
    </style>

    <div class="pos">
        {{-- ========= المنتجات ========= --}}
        <div class="pos-card">
            <input type="search" class="pos-input" placeholder="🔍 ابحث عن صنف..." wire:model.live.debounce.300ms="search" autofocus>

            <div class="pos-row pos-cats" style="margin-top:10px">
                <button class="pos-chip {{ $categoryId === null ? 'active' : '' }}" wire:click="$set('categoryId', null)">الكل</button>
                @foreach($this->categories as $cat)
                    <button class="pos-chip {{ $categoryId === $cat->id ? 'active' : '' }}" wire:click="$set('categoryId', {{ $cat->id }})">{{ $cat->name }}</button>
                @endforeach
            </div>

            <div class="pos-grid">
                @forelse($this->products as $product)
                    <button type="button" class="pos-product" wire:click="addToCart({{ $product->id }})" wire:key="p-{{ $product->id }}">
                        @if($product->image)
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($product->image) }}" alt="">
                        @else
                            <div class="ph">🍽️</div>
                        @endif
                        <div class="n">{{ $product->name }}</div>
                        <div class="p">{{ number_format($product->price, 2) }}</div>
                    </button>
                @empty
                    <div class="pos-empty" style="grid-column:1/-1">لا توجد أصناف</div>
                @endforelse
            </div>
        </div>

        {{-- ========= الطلب ========= --}}
        <div class="pos-card">
            <div class="pos-types">
                @foreach(\App\Enums\OrderType::cases() as $type)
                    <button type="button" class="{{ $orderType === $type->value ? 'active' : '' }}" wire:click="setType('{{ $type->value }}')">
                        {{ $type->getLabel() }}
                    </button>
                @endforeach
            </div>

            {{-- صالة: اختيار الطاولة --}}
            @if($orderType === 'dine_in')
                <div class="pos-tables">
                    @foreach($this->tables as $table)
                        <button type="button" wire:key="t-{{ $table->id }}"
                                class="pos-table {{ $table->status === \App\Enums\TableStatus::Occupied ? 'busy' : '' }} {{ $tableId === $table->id ? 'sel' : '' }}"
                                wire:click="selectTable({{ $table->id }})">{{ $table->name }}</button>
                    @endforeach
                </div>
                @if($open)
                    <div class="pos-alert">
                        الطاولة عليها طلب مفتوح #{{ $open->number }} بقيمة {{ number_format($open->total, 2) }} — الأصناف الجديدة ستضاف عليه.
                    </div>
                @endif
            @endif

            {{-- بيانات العميل --}}
            @if($orderType !== 'dine_in')
                <span class="pos-label">العميل {{ $orderType === 'takeaway' ? '(اختياري)' : '' }}</span>
                <div class="pos-2">
                    <input class="pos-input" placeholder="📞 الموبايل" wire:model.live.debounce.600ms="customerPhone" inputmode="tel">
                    <input class="pos-input" placeholder="الاسم" wire:model="customerName">
                </div>
            @endif

            @if($orderType === 'delivery')
                <div class="pos-2" style="margin-top:8px">
                    <select class="pos-input" wire:model.live="deliveryAreaId">
                        <option value="">-- المنطقة --</option>
                        @foreach($this->areas as $area)
                            <option value="{{ $area->id }}">{{ $area->name }} ({{ number_format($area->fee, 0) }})</option>
                        @endforeach
                    </select>
                    <select class="pos-input" wire:model="driverId">
                        <option value="">-- الطيار (لاحقاً) --</option>
                        @foreach($this->drivers as $driver)
                            <option value="{{ $driver->id }}">{{ $driver->name }}</option>
                        @endforeach
                    </select>
                </div>
                <textarea class="pos-input" style="margin-top:8px" rows="2" placeholder="العنوان بالتفصيل" wire:model="customerAddress"></textarea>
            @endif

            {{-- السلة --}}
            <div class="pos-cart">
                @forelse($cart as $i => $item)
                    <div class="pos-item" wire:key="c-{{ $i }}-{{ $item['product_id'] }}">
                        <div class="top">
                            <div style="flex:1">
                                <div style="font-weight:600">{{ $item['name'] }}</div>
                                <div style="font-size:12px;color:#6b7280">{{ number_format($item['price'], 2) }} × {{ $item['qty'] }} = <b>{{ number_format($item['price'] * $item['qty'], 2) }}</b></div>
                            </div>
                            <div class="pos-qty">
                                <button type="button" wire:click="increment({{ $i }})">+</button>
                                <span style="min-width:22px;text-align:center;font-weight:700">{{ $item['qty'] }}</span>
                                <button type="button" wire:click="decrement({{ $i }})">−</button>
                                <button type="button" class="del" wire:click="removeItem({{ $i }})">✕</button>
                            </div>
                        </div>
                        <input class="pos-input pos-note" placeholder="ملاحظة (بدون بصل، سبايسي...)" wire:model.blur="cart.{{ $i }}.notes">
                    </div>
                @empty
                    <div class="pos-empty">🛒 السلة فارغة — اضغط على الأصناف لإضافتها</div>
                @endforelse
            </div>

            {{-- الخصم والملاحظات --}}
            <div class="pos-2">
                <div>
                    <span class="pos-label">الخصم</span>
                    <div style="display:flex;gap:4px">
                        <input type="number" min="0" step="0.5" class="pos-input" wire:model.live.debounce.400ms="discount">
                        <select class="pos-input" style="width:70px" wire:model.live="discountType">
                            <option value="amount">{{ $cur }}</option>
                            <option value="percent">%</option>
                        </select>
                    </div>
                </div>
                <div>
                    <span class="pos-label">ملاحظات الطلب</span>
                    <input class="pos-input" wire:model="notes">
                </div>
            </div>

            {{-- الإجماليات --}}
            <div class="pos-sum" style="margin-top:10px">
                <div><span>المجموع</span><span>{{ number_format($t['subtotal'], 2) }}</span></div>
                @if($t['discount'] > 0)<div><span>الخصم</span><span>- {{ number_format($t['discount'], 2) }}</span></div>@endif
                @if($t['service'] > 0)<div><span>الخدمة</span><span>{{ number_format($t['service'], 2) }}</span></div>@endif
                @if($t['tax'] > 0)<div><span>الضريبة</span><span>{{ number_format($t['tax'], 2) }}</span></div>@endif
                @if($t['delivery_fee'] > 0)<div><span>التوصيل</span><span>{{ number_format($t['delivery_fee'], 2) }}</span></div>@endif
                <div class="grand"><span>الإجمالي</span><span>{{ number_format($t['total'], 2) }} {{ $cur }}</span></div>
                @if($open)
                    <div style="font-weight:700"><span>إجمالي الطاولة بعد الإضافة</span><span>{{ number_format($t['total'] + $open->total, 2) }}</span></div>
                @endif
            </div>

            {{-- الدفع --}}
            <span class="pos-label">طريقة الدفع</span>
            <div class="pos-row">
                @foreach(\App\Enums\PaymentMethod::cases() as $method)
                    <button type="button" class="pos-chip {{ $paymentMethod === $method->value ? 'active' : '' }}" wire:click="$set('paymentMethod', '{{ $method->value }}')">{{ $method->getLabel() }}</button>
                @endforeach
            </div>

            @if($paymentMethod === 'cash')
                <div class="pos-2" style="margin-top:8px;align-items:center">
                    <input type="number" min="0" step="0.5" class="pos-input" placeholder="المبلغ المستلم" wire:model.live.debounce.400ms="paidAmount">
                    <div style="font-weight:700">الباقي: <span style="color:#16a34a">{{ number_format($t['change'], 2) }}</span></div>
                </div>
                <div class="pos-row" style="margin-top:6px">
                    @foreach([50, 100, 200, 500] as $bill)
                        <button type="button" class="pos-chip" style="padding:4px 10px" wire:click="quickPay({{ $bill }})">{{ $bill }}</button>
                    @endforeach
                </div>
            @endif

            <label class="pos-row" style="margin-top:10px;font-size:13px;cursor:pointer">
                <input type="checkbox" wire:model="printKitchen"> طباعة بون للمطبخ
            </label>

            <div style="display:grid;gap:8px;margin-top:10px">
                <button type="button" class="pos-btn pay" wire:click="checkout(true)" wire:loading.attr="disabled" @disabled(empty($cart))>
                    💵 دفع وطباعة ({{ number_format($open ? $t['total'] + $open->total : $t['total'], 2) }})
                </button>
                <div class="pos-2">
                    <button type="button" class="pos-btn hold" wire:click="checkout(false)" wire:loading.attr="disabled" @disabled(empty($cart))>
                        📝 حفظ بدون دفع
                    </button>
                    <button type="button" class="pos-btn clear" wire:click="clearCart" wire:confirm="مسح الطلب الحالي؟">🗑️ مسح</button>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
