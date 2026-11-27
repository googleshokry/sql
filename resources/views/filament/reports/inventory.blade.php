<x-filament-panels::page>
    @include('filament.reports._head', ['title' => 'تقييم المخزون'])
    @php($t = $data['totals'])
    <x-filament::section>
        <div class="erp-filters">
            <div>
                <label>المخزن</label>
                <select wire:model.live="warehouse">
                    <option value="">كل المخازن</option>
                    @foreach($data['warehouses'] as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                </select>
            </div>
            <div><label>&nbsp;</label><label style="display:flex;gap:.4rem;align-items:center"><input type="checkbox" wire:model.live="lowOnly" style="min-width:auto"> الأصناف التي وصلت لحد الطلب فقط</label></div>
        </div>
        <div class="erp-kpis">
            <div class="erp-kpi"><div>عدد الأصناف</div><div class="v">{{ count($data['rows']) }}</div></div>
            <div class="erp-kpi"><div>قيمة المخزون بالتكلفة</div><div class="v">{{ number_format($t['value'], 2) }}</div></div>
            <div class="erp-kpi"><div>قيمة المخزون بسعر البيع</div><div class="v">{{ number_format($t['sale_value'], 2) }}</div></div>
        </div>
        <table class="erp-table">
            <thead><tr><th>الكود</th><th>الصنف</th><th>التصنيف</th><th>الوحدة</th><th class="num">الكمية</th><th class="num">حد الطلب</th><th class="num">متوسط التكلفة</th><th class="num">القيمة</th></tr></thead>
            <tbody>
                @foreach($data['rows'] as $r)
                    <tr>
                        <td>{{ $r['sku'] }}</td><td>{{ $r['name'] }}</td><td>{{ $r['category'] }}</td><td>{{ $r['unit'] }}</td>
                        <td class="num {{ $r['qty'] <= $r['min'] ? 'text-danger' : '' }}">{{ $r['qty'] }}</td>
                        <td class="num">{{ $r['min'] }}</td>
                        <td class="num">{{ number_format($r['cost'], 2) }}</td>
                        <td class="num">{{ number_format($r['value'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot><tr><td colspan="4">الإجمالي</td><td class="num">{{ $t['qty'] }}</td><td></td><td></td><td class="num">{{ number_format($t['value'], 2) }}</td></tr></tfoot>
        </table>
    </x-filament::section>
</x-filament-panels::page>
