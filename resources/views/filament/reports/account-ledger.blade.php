<x-filament-panels::page>
    @include('filament.reports._head', ['title' => 'دفتر الأستاذ'])
    <x-filament::section>
        <div class="erp-filters">
            <div>
                <label>الحساب</label>
                <select wire:model.live="account" style="min-width:280px">
                    <option value="">— اختر الحساب —</option>
                    @foreach($data['options'] as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                </select>
            </div>
            @include('filament.reports._dates')
        </div>
        @if($data['record'])
            <table class="erp-table">
                <thead><tr><th>التاريخ</th><th>رقم القيد</th><th>الحساب</th><th>البيان</th><th class="num">مدين</th><th class="num">دائن</th><th class="num">الرصيد</th></tr></thead>
                <tbody>
                    <tr class="group"><td colspan="6">الرصيد الافتتاحي</td><td class="num">{{ number_format($data['opening'], 2) }}</td></tr>
                    @foreach($data['rows'] as $r)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($r['date'])->format('Y-m-d') }}</td>
                            <td><a href="{{ \App\Filament\Resources\JournalEntryResource::getUrl('view', ['record' => $r['entry_id']]) }}" style="color:rgb(79,70,229)">{{ $r['number'] }}</a></td>
                            <td>{{ $r['account_name'] }}</td>
                            <td>{{ $r['description'] }} {{ $r['memo'] }}</td>
                            <td class="num">{{ (float) $r['debit'] ? number_format($r['debit'], 2) : '' }}</td>
                            <td class="num">{{ (float) $r['credit'] ? number_format($r['credit'], 2) : '' }}</td>
                            <td class="num">{{ number_format($r['balance'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot><tr><td colspan="4">الإجمالي</td><td class="num">{{ number_format($data['totals'][0], 2) }}</td><td class="num">{{ number_format($data['totals'][1], 2) }}</td><td class="num">{{ number_format($data['closing'], 2) }}</td></tr></tfoot>
            </table>
        @else
            <div style="text-align:center;padding:2rem;opacity:.6">اختر الحساب</div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
