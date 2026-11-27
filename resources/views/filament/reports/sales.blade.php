<x-filament-panels::page>
    @include('filament.reports._head', ['title' => __('تقرير المبيعات')])
    @php($t = $data['totals'])
    <x-filament::section>
        <div class="erp-filters">
            <div>
                <label>{{ __('التجميع') }}</label>
                <select wire:model.live="groupBy">
                    @foreach(\App\Filament\Pages\Reports\SalesReport::GROUPS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                </select>
            </div>
            @include('filament.reports._dates')
        </div>
        <div class="erp-kpis">
            <div class="erp-kpi"><div>{{ __('صافي المبيعات') }}</div><div class="v">{{ number_format($t['revenue'], 2) }}</div></div>
            <div class="erp-kpi"><div>{{ __('التكلفة') }}</div><div class="v">{{ number_format($t['cost'], 2) }}</div></div>
            <div class="erp-kpi"><div>{{ __('الربح') }}</div><div class="v text-success">{{ number_format($t['profit'], 2) }}</div></div>
            <div class="erp-kpi"><div>{{ __('الضريبة') }}</div><div class="v">{{ number_format($t['tax'], 2) }}</div></div>
        </div>
        <table class="erp-table">
            <thead><tr><th>{{ __('البيان') }}</th><th class="num">{{ __('عدد الفواتير') }}</th><th class="num">{{ __('الكمية') }}</th><th class="num">{{ __('صافي المبيعات') }}</th><th class="num">{{ __('التكلفة') }}</th><th class="num">{{ __('الربح') }}</th><th class="num">{{ __('هامش') }} %</th></tr></thead>
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
                    <tr><td colspan="7" style="text-align:center;opacity:.6">{{ __('لا توجد مبيعات مرحّلة في هذه الفترة') }}</td></tr>
                @endforelse
            </tbody>
            <tfoot><tr><td>{{ __('الإجمالي') }}</td><td class="num">{{ $t['invoices'] }}</td><td class="num">{{ (float) $t['qty'] }}</td><td class="num">{{ number_format($t['revenue'], 2) }}</td><td class="num">{{ number_format($t['cost'], 2) }}</td><td class="num">{{ number_format($t['profit'], 2) }}</td><td class="num">{{ $t['revenue'] > 0 ? number_format($t['profit'] / $t['revenue'] * 100, 1) : 0 }}%</td></tr></tfoot>
        </table>
    </x-filament::section>
</x-filament-panels::page>
