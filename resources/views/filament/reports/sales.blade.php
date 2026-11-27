<x-filament-panels::page>
    @include('filament.reports._head', ['title' => 'تقرير المبيعات'])
    @php($t = $data['totals'])
    <x-filament::section>
        <div class="erp-filters">
            <div>
                <label>التجميع</label>
                <select wire:model.live="groupBy">
                    @foreach(\App\Filament\Pages\Reports\SalesReport::GROUPS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                </select>
            </div>
            @include('filament.reports._dates')
        </div>
        <div class="erp-kpis">
            <div class="erp-kpi"><div>صافي المبيعات</div><div class="v">{{ number_format($t['revenue'], 2) }}</div></div>
            <div class="erp-kpi"><div>التكلفة</div><div class="v">{{ number_format($t['cost'], 2) }}</div></div>
            <div class="erp-kpi"><div>الربح</div><div class="v text-success">{{ number_format($t['profit'], 2) }}</div></div>
            <div class="erp-kpi"><div>الضريبة</div><div class="v">{{ number_format($t['tax'], 2) }}</div></div>
        </div>
        <table class="erp-table">
            <thead><tr><th>البيان</th><th class="num">عدد الفواتير</th><th class="num">الكمية</th><th class="num">صافي المبيعات</th><th class="num">التكلفة</th><th class="num">الربح</th><th class="num">هامش %</th></tr></thead>
            <tbody>
                @forelse($data['rows'] as $r)
                    <tr>
                        <td>{{ $r['label'] }}</td>
                        <td class="num">{{ $r['invoices'] }}</td>
                        <td class="num">{{ (float) $r['qty'] }}</td>
                        <td class="num">{{ number_format($r['revenue'], 2) }}</td>
                        <td class="num">{{ number_format($r['cost'], 2) }}</td>
                        <td class="num {{ $r['profit'] < 0 ? 'text-danger' : '' }}">{{ number_format($r['profit'], 2) }}</td>
                        <td class="num">{{ $r['revenue'] > 0 ? number_format($r['profit'] / $r['revenue'] * 100, 1) : 0 }}%</td>
                    </tr>
                @empty
                    <tr><td colspan="7" style="text-align:center;opacity:.6">لا توجد مبيعات مرحّلة في هذه الفترة</td></tr>
                @endforelse
            </tbody>
            <tfoot><tr><td>الإجمالي</td><td class="num">{{ $t['invoices'] }}</td><td class="num">{{ (float) $t['qty'] }}</td><td class="num">{{ number_format($t['revenue'], 2) }}</td><td class="num">{{ number_format($t['cost'], 2) }}</td><td class="num">{{ number_format($t['profit'], 2) }}</td><td class="num">{{ $t['revenue'] > 0 ? number_format($t['profit'] / $t['revenue'] * 100, 1) : 0 }}%</td></tr></tfoot>
        </table>
    </x-filament::section>
</x-filament-panels::page>
