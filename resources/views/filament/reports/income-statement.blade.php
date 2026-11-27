<x-filament-panels::page>
    @include('filament.reports._head', ['title' => 'قائمة الدخل'])
    @php($d = $data)
    <x-filament::section>
        <div class="erp-filters">@include('filament.reports._dates')</div>
        <div class="erp-kpis">
            <div class="erp-kpi"><div>صافي المبيعات</div><div class="v">{{ number_format($d['netSales'], 2) }}</div></div>
            <div class="erp-kpi"><div>مجمل الربح</div><div class="v">{{ number_format($d['gross'], 2) }}</div></div>
            <div class="erp-kpi"><div>هامش الربح</div><div class="v">{{ $d['netSales'] ? number_format($d['gross'] / $d['netSales'] * 100, 1) : 0 }}%</div></div>
            <div class="erp-kpi"><div>صافي الربح</div><div class="v {{ $d['net'] >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($d['net'], 2) }}</div></div>
        </div>
        <table class="erp-table" style="max-width:720px">
            <tbody>
                <tr><td>إيرادات المبيعات</td><td class="num">{{ number_format($d['sales'], 2) }}</td></tr>
                <tr><td>يخصم: مردودات المبيعات</td><td class="num">({{ number_format($d['returns'], 2) }})</td></tr>
                <tr class="group"><td>صافي المبيعات</td><td class="num">{{ number_format($d['netSales'], 2) }}</td></tr>
                <tr><td>يخصم: تكلفة البضاعة المباعة</td><td class="num">({{ number_format($d['cogs'], 2) }})</td></tr>
                <tr class="group"><td>مجمل الربح</td><td class="num">{{ number_format($d['gross'], 2) }}</td></tr>
                @foreach($d['otherRevenue'] as $name => $v)<tr><td>يضاف: {{ $name }}</td><td class="num">{{ number_format($v, 2) }}</td></tr>@endforeach
                <tr class="group"><td colspan="2">المصروفات التشغيلية</td></tr>
                @foreach($d['expenses'] as $name => $v)<tr><td>&nbsp;&nbsp;{{ $name }}</td><td class="num">({{ number_format($v, 2) }})</td></tr>@endforeach
                <tr class="group"><td>إجمالي المصروفات</td><td class="num">({{ number_format(array_sum($d['expenses']), 2) }})</td></tr>
            </tbody>
            <tfoot><tr><td>صافي {{ $d['net'] >= 0 ? 'الربح' : 'الخسارة' }}</td><td class="num">{{ number_format($d['net'], 2) }} {{ \App\Filament\Support\Money::currency() }}</td></tr></tfoot>
        </table>
    </x-filament::section>
</x-filament-panels::page>
