<x-filament-panels::page>
    @include('filament.reports._head', ['title' => 'الإقرار الضريبي'])
    @php($d = $data)
    <x-filament::section>
        <div class="erp-filters">@include('filament.reports._dates')</div>
        <table class="erp-table" style="max-width:820px">
            <thead><tr><th>البند</th><th class="num">المبلغ الخاضع للضريبة</th><th class="num">مبلغ الضريبة</th></tr></thead>
            <tbody>
                <tr class="group"><td colspan="3">ضريبة المخرجات (المبيعات)</td></tr>
                <tr><td>المبيعات الخاضعة للضريبة</td><td class="num">{{ number_format($d['sales']->base, 2) }}</td><td class="num">{{ number_format($d['sales']->vat, 2) }}</td></tr>
                <tr><td>يخصم: مردودات المبيعات</td><td class="num">({{ number_format($d['salesReturns']->base, 2) }})</td><td class="num">({{ number_format($d['salesReturns']->vat, 2) }})</td></tr>
                <tr class="group"><td>إجمالي ضريبة المخرجات</td><td></td><td class="num">{{ number_format($d['output'], 2) }}</td></tr>
                <tr class="group"><td colspan="3">ضريبة المدخلات (المشتريات والمصروفات)</td></tr>
                <tr><td>المشتريات الخاضعة للضريبة</td><td class="num">{{ number_format($d['purchases']->base, 2) }}</td><td class="num">{{ number_format($d['purchases']->vat, 2) }}</td></tr>
                <tr><td>يخصم: مردودات المشتريات</td><td class="num">({{ number_format($d['purchaseReturns']->base, 2) }})</td><td class="num">({{ number_format($d['purchaseReturns']->vat, 2) }})</td></tr>
                <tr><td>المصروفات الخاضعة للضريبة</td><td class="num">{{ number_format($d['expenses']->base, 2) }}</td><td class="num">{{ number_format($d['expenses']->vat, 2) }}</td></tr>
                <tr class="group"><td>إجمالي ضريبة المدخلات</td><td></td><td class="num">{{ number_format($d['input'], 2) }}</td></tr>
            </tbody>
            <tfoot><tr><td colspan="2">صافي الضريبة {{ $d['net'] >= 0 ? 'المستحقة للهيئة' : 'القابلة للاسترداد' }}</td><td class="num">{{ number_format($d['net'], 2) }} {{ \App\Filament\Support\Money::currency() }}</td></tr></tfoot>
        </table>
    </x-filament::section>
</x-filament-panels::page>
