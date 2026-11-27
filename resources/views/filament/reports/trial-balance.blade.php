<x-filament-panels::page>
    @include('filament.reports._head', ['title' => __('ميزان المراجعة')])
    @php($t = $data['totals'])
    <x-filament::section>
        <div class="erp-filters">@include('filament.reports._dates')</div>
        <table class="erp-table">
            <thead>
                <tr><th rowspan="2">{{ __('رقم الحساب') }}</th><th rowspan="2">{{ __('اسم الحساب') }}</th><th colspan="2" style="text-align:center">{{ __('رصيد أول المدة') }}</th><th colspan="2" style="text-align:center">{{ __('حركة الفترة') }}</th><th colspan="2" style="text-align:center">{{ __('الرصيد الختامي') }}</th></tr>
                <tr><th class="num">{{ __('مدين') }}</th><th class="num">{{ __('دائن') }}</th><th class="num">{{ __('مدين') }}</th><th class="num">{{ __('دائن') }}</th><th class="num">{{ __('مدين') }}</th><th class="num">{{ __('دائن') }}</th></tr>
            </thead>
            <tbody>
                @foreach($data['rows'] as $r)
                    <tr>
                        <td>{{ $r['code'] }}</td>
                        <td><a href="{{ \App\Filament\Pages\Reports\AccountLedger::getUrl(['account' => $r['id'], 'from' => $this->from, 'to' => $this->to]) }}" style="color:rgb(79,70,229)">{{ $r['name'] }}</a></td>
                        @foreach(['od','oc','pd','pc','cd','cc'] as $k)<td class="num">{{ $r[$k] ? number_format($r[$k], 2) : '-' }}</td>@endforeach
                    </tr>
                @endforeach
            </tbody>
            <tfoot><tr><td colspan="2">{{ __('الإجمالي') }}</td>@foreach(['od','oc','pd','pc','cd','cc'] as $k)<td class="num">{{ number_format($t[$k], 2) }}</td>@endforeach</tr></tfoot>
        </table>
        <div style="margin-top:.75rem" class="{{ abs($t['cd'] - $t['cc']) < 0.01 ? 'text-success' : 'text-danger' }}">
            {{ abs($t['cd'] - $t['cc']) < 0.01 ? __('✔ الميزان متوازن') : __('✘ الميزان غير متوازن') }}
        </div>
    </x-filament::section>
</x-filament-panels::page>
