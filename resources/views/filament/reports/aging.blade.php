<x-filament-panels::page>
    @include('filament.reports._head', ['title' => __('أعمار الديون')])
    <x-filament::section>
        <div class="erp-filters">
            <div>
                <label>{{ __('النوع') }}</label>
                <select wire:model.live="party">
                    <option value="customer">{{ __('ذمم العملاء (مدينة)') }}</option>
                    <option value="supplier">{{ __('ذمم الموردين (دائنة)') }}</option>
                </select>
            </div>
        </div>
        <table class="erp-table">
            <thead><tr><th>{{ __('الاسم') }}</th><th class="num">{{ __('عدد الفواتير') }}</th>@foreach($data['buckets'] as $b)<th class="num">{{ $b }} {{ __('يوم') }}</th>@endforeach<th class="num">{{ __('الإجمالي') }}</th></tr></thead>
            <tbody>
                @forelse($data['rows'] as $name => $r)
                    <tr><td>{{ $name }}</td><td class="num">{{ $r['count'] }}</td>@foreach($data['buckets'] as $b)<td class="num {{ $b === '90+' && $r[$b] > 0 ? 'text-danger' : '' }}">{{ $r[$b] ? number_format($r[$b], 2) : '-' }}</td>@endforeach<td class="num">{{ number_format($r['total'], 2) }}</td></tr>
                @empty
                    <tr><td colspan="7" style="text-align:center;opacity:.6">{{ __('لا توجد مبالغ مستحقة') }}</td></tr>
                @endforelse
            </tbody>
            <tfoot><tr><td colspan="2">{{ __('الإجمالي') }}</td>@foreach($data['buckets'] as $b)<td class="num">{{ number_format($data['totals'][$b], 2) }}</td>@endforeach<td class="num">{{ number_format($data['totals']['total'], 2) }}</td></tr></tfoot>
        </table>
    </x-filament::section>
</x-filament-panels::page>
