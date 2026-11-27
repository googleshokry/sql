<x-filament-panels::page>
    @include('filament.reports._head', ['title' => __('الإقرار الضريبي')])
    @php($d = $data)
    <x-filament::section>
        <div class="erp-filters">@include('filament.reports._dates')</div>
        <table class="erp-table" style="max-width:820px">
            <thead><tr><th>{{ __('البند') }}</th><th class="num">{{ __('المبلغ الخاضع للضريبة') }}</th><th class="num">{{ __('مبلغ الضريبة') }}</th></tr></thead>
            <tbody>
                <tr class="group"><td colspan="3">{{ __('ضريبة المخرجات (المبيعات)') }}</td></tr>
                <tr><td>{{ __('المبيعات الخاضعة للضريبة') }}</td><td class="num">{{ number_format($d['sales']->base, 2) }}</td><td class="num">{{ number_format($d['sales']->vat, 2) }}</td></tr>
                <tr><td>{{ __('يخصم: مردودات المبيعات') }}</td><td class="num">({{ number_format($d['salesReturns']->base, 2) }})</td><td class="num">({{ number_format($d['salesReturns']->vat, 2) }})</td></tr>
                <tr class="group"><td>{{ __('إجمالي ضريبة المخرجات') }}</td><td></td><td class="num">{{ number_format($d['output'], 2) }}</td></tr>
                <tr class="group"><td colspan="3">{{ __('ضريبة المدخلات (المشتريات والمصروفات)') }}</td></tr>
                <tr><td>{{ __('المشتريات الخاضعة للضريبة') }}</td><td class="num">{{ number_format($d['purchases']->base, 2) }}</td><td class="num">{{ number_format($d['purchases']->vat, 2) }}</td></tr>
                <tr><td>{{ __('يخصم: مردودات المشتريات') }}</td><td class="num">({{ number_format($d['purchaseReturns']->base, 2) }})</td><td class="num">({{ number_format($d['purchaseReturns']->vat, 2) }})</td></tr>
                <tr><td>{{ __('المصروفات الخاضعة للضريبة') }}</td><td class="num">{{ number_format($d['expenses']->base, 2) }}</td><td class="num">{{ number_format($d['expenses']->vat, 2) }}</td></tr>
                <tr class="group"><td>{{ __('إجمالي ضريبة المدخلات') }}</td><td></td><td class="num">{{ number_format($d['input'], 2) }}</td></tr>
            </tbody>
            <tfoot><tr><td colspan="2">{{ __('صافي الضريبة') }} {{ $d['net'] >= 0 ? __('المستحقة للهيئة') : __('القابلة للاسترداد') }}</td><td class="num">{{ number_format($d['net'], 2) }} {{ \App\Filament\Support\Money::currency() }}</td></tr></tfoot>
        </table>
    </x-filament::section>
</x-filament-panels::page>
