<x-filament-panels::page>
    @include('filament.reports._head', ['title' => __('قائمة الدخل')])
    @php($d = $data)
    <x-filament::section>
        <div class="erp-filters">@include('filament.reports._dates')</div>
        <div class="erp-kpis">
            <div class="erp-kpi"><div>{{ __('صافي المبيعات') }}</div><div class="v">{{ number_format($d['netSales'], 2) }}</div></div>
            <div class="erp-kpi"><div>{{ __('مجمل الربح') }}</div><div class="v">{{ number_format($d['gross'], 2) }}</div></div>
            <div class="erp-kpi"><div>{{ __('هامش الربح') }}</div><div class="v">{{ $d['netSales'] ? number_format($d['gross'] / $d['netSales'] * 100, 1) : 0 }}%</div></div>
            <div class="erp-kpi"><div>{{ __('صافي الربح') }}</div><div class="v {{ $d['net'] >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($d['net'], 2) }}</div></div>
        </div>
        <table class="erp-table" style="max-width:720px">
            <tbody>
                <tr><td>{{ __('إيرادات المبيعات') }}</td><td class="num">{{ number_format($d['sales'], 2) }}</td></tr>
                <tr><td>{{ __('يخصم: مردودات المبيعات') }}</td><td class="num">({{ number_format($d['returns'], 2) }})</td></tr>
                <tr class="group"><td>{{ __('صافي المبيعات') }}</td><td class="num">{{ number_format($d['netSales'], 2) }}</td></tr>
                <tr><td>{{ __('يخصم: تكلفة البضاعة المباعة') }}</td><td class="num">({{ number_format($d['cogs'], 2) }})</td></tr>
                <tr class="group"><td>{{ __('مجمل الربح') }}</td><td class="num">{{ number_format($d['gross'], 2) }}</td></tr>
                @foreach($d['otherRevenue'] as $name => $v)<tr><td>{{ __('يضاف:') }} {{ $name }}</td><td class="num">{{ number_format($v, 2) }}</td></tr>@endforeach
                <tr class="group"><td colspan="2">{{ __('المصروفات التشغيلية') }}</td></tr>
                @foreach($d['expenses'] as $name => $v)<tr><td>&nbsp;&nbsp;{{ $name }}</td><td class="num">({{ number_format($v, 2) }})</td></tr>@endforeach
                <tr class="group"><td>{{ __('إجمالي المصروفات') }}</td><td class="num">({{ number_format(array_sum($d['expenses']), 2) }})</td></tr>
            </tbody>
            <tfoot><tr><td>{{ __('صافي') }} {{ $d['net'] >= 0 ? __('الربح') : __('الخسارة') }}</td><td class="num">{{ number_format($d['net'], 2) }} {{ \App\Filament\Support\Money::currency() }}</td></tr></tfoot>
        </table>
    </x-filament::section>
</x-filament-panels::page>
