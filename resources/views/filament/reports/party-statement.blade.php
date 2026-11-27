<x-filament-panels::page>
    @include('filament.reports._head', ['title' => 'كشف حساب'])
    @php($c = \App\Filament\Support\Money::currency())
    <x-filament::section>
        <div class="erp-filters">
            <div>
                <label>النوع</label>
                <select wire:model.live="party">
                    <option value="customer">عميل</option>
                    <option value="supplier">مورد</option>
                </select>
            </div>
            <div>
                <label>الاسم</label>
                <select wire:model.live="id">
                    <option value="">— اختر —</option>
                    @foreach($data['options'] as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                </select>
            </div>
            @include('filament.reports._dates')
        </div>

        @if($data['record'])
            <div class="erp-kpis">
                <div class="erp-kpi"><div>الرصيد الافتتاحي</div><div class="v">{{ number_format($data['opening'], 2) }}</div></div>
                <div class="erp-kpi"><div>إجمالي المدين</div><div class="v">{{ number_format($data['totals'][0], 2) }}</div></div>
                <div class="erp-kpi"><div>إجمالي الدائن</div><div class="v">{{ number_format($data['totals'][1], 2) }}</div></div>
                <div class="erp-kpi"><div>الرصيد الختامي</div><div class="v {{ $data['closing'] > 0 ? 'text-danger' : 'text-success' }}">{{ number_format($data['closing'], 2) }} {{ $c }}</div></div>
            </div>
            <table class="erp-table">
                <thead><tr><th>التاريخ</th><th>رقم القيد</th><th>البيان</th><th class="num">مدين</th><th class="num">دائن</th><th class="num">الرصيد</th></tr></thead>
                <tbody>
                    <tr class="group"><td colspan="5">رصيد ما قبل الفترة</td><td class="num">{{ number_format($data['opening'], 2) }}</td></tr>
                    @forelse($data['rows'] as $r)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($r['date'])->format('Y-m-d') }}</td>
                            <td>{{ $r['number'] }}</td>
                            <td>{{ $r['description'] }}</td>
                            <td class="num">{{ $r['debit'] ? number_format($r['debit'], 2) : '' }}</td>
                            <td class="num">{{ $r['credit'] ? number_format($r['credit'], 2) : '' }}</td>
                            <td class="num">{{ number_format($r['balance'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" style="text-align:center;opacity:.6">لا توجد حركات في هذه الفترة</td></tr>
                    @endforelse
                </tbody>
                <tfoot><tr><td colspan="3">الإجمالي</td><td class="num">{{ number_format($data['totals'][0], 2) }}</td><td class="num">{{ number_format($data['totals'][1], 2) }}</td><td class="num">{{ number_format($data['closing'], 2) }}</td></tr></tfoot>
            </table>
        @else
            <div style="text-align:center;padding:2rem;opacity:.6">اختر العميل أو المورد لعرض كشف الحساب</div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
