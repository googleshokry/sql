<x-filament-panels::page>
    @include('filament.reports._head', ['title' => __('الميزانية العمومية')])
    @php($d = $data)
    <x-filament::section>
        <div class="erp-filters"><div><label>{{ __('كما في تاريخ') }}</label><input type="date" wire:model.live="to"></div></div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:1rem">
            <table class="erp-table">
                <thead><tr><th colspan="2">{{ __('الأصول') }}</th></tr></thead>
                <tbody>@foreach($d['assets'] as $r)<tr><td>{{ $r['code'] }} - {{ $r['name'] }}</td><td class="num">{{ number_format($r['value'], 2) }}</td></tr>@endforeach</tbody>
                <tfoot><tr><td>{{ __('إجمالي الأصول') }}</td><td class="num">{{ number_format($d['totalAssets'], 2) }}</td></tr></tfoot>
            </table>
            <table class="erp-table">
                <thead><tr><th colspan="2">{{ __('الخصوم وحقوق الملكية') }}</th></tr></thead>
                <tbody>
                    @foreach($d['liabilities'] as $r)<tr><td>{{ $r['code'] }} - {{ $r['name'] }}</td><td class="num">{{ number_format($r['value'], 2) }}</td></tr>@endforeach
                    <tr class="group"><td>{{ __('إجمالي الخصوم') }}</td><td class="num">{{ number_format($d['totalLiabilities'], 2) }}</td></tr>
                    @foreach($d['equity'] as $r)<tr><td>{{ $r['code'] }} - {{ $r['name'] }}</td><td class="num">{{ number_format($r['value'], 2) }}</td></tr>@endforeach
                    <tr><td>{{ __('أرباح (خسائر) الفترة') }}</td><td class="num">{{ number_format($d['profit'], 2) }}</td></tr>
                    <tr class="group"><td>{{ __('إجمالي حقوق الملكية') }}</td><td class="num">{{ number_format($d['totalEquity'], 2) }}</td></tr>
                </tbody>
                <tfoot><tr><td>{{ __('إجمالي الخصوم وحقوق الملكية') }}</td><td class="num">{{ number_format($d['totalLiabilities'] + $d['totalEquity'], 2) }}</td></tr></tfoot>
            </table>
        </div>
        @php($diff = $d['totalAssets'] - $d['totalLiabilities'] - $d['totalEquity'])
        <div style="margin-top:.75rem" class="{{ abs($diff) < 0.01 ? 'text-success' : 'text-danger' }}">{{ abs($diff) < 0.01 ? __('✔ الميزانية متوازنة') : __('✘ فرق: ').number_format($diff, 2) }}</div>
    </x-filament::section>
</x-filament-panels::page>
