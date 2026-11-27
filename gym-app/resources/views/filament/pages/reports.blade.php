@php
    use App\Models\Payment;
    use App\Support\Money;
    $r = $this->report;
@endphp
<x-filament-panels::page>
    <style>
        .rp-bar { display:flex; gap:.75rem; flex-wrap:wrap; align-items:end; }
        .rp-bar label { display:flex; flex-direction:column; font-size:.85rem; gap:.25rem; }
        .rp-bar input { padding:.45rem .6rem; border-radius:.5rem; border:1px solid rgba(127,127,127,.35); background:transparent; }
        .rp-cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:1rem; }
        .rp-card { padding:1rem; border-radius:.75rem; background:rgba(127,127,127,.07); }
        .rp-card span { font-size:.85rem; opacity:.75; } .rp-card b { display:block; font-size:1.35rem; margin-top:.25rem; }
        .rp-2 { display:grid; grid-template-columns:1fr; gap:1rem; } @media(min-width:1024px){ .rp-2 { grid-template-columns:1fr 1fr; } }
        .rp-t { width:100%; border-collapse:collapse; } .rp-t td, .rp-t th { padding:.5rem; border-bottom:1px solid rgba(127,127,127,.15); text-align:start; }
        @media print { .fi-sidebar, .fi-topbar, .rp-bar, .no-print { display:none !important; } }
    </style>

    <x-filament::section>
        <div class="rp-bar">
            <label>{{ __('من') }} <input type="date" wire:model.live="from"></label>
            <label>{{ __('إلى') }} <input type="date" wire:model.live="until"></label>
            @foreach (['today' => __('اليوم'), 'yesterday' => __('أمس'), 'week' => __('هذا الأسبوع'), 'month' => __('هذا الشهر'), 'last_month' => __('الشهر الماضي'), 'year' => __('هذه السنة')] as $k => $label)
                <x-filament::button size="sm" color="gray" wire:click="preset('{{ $k }}')">{{ $label }}</x-filament::button>
            @endforeach
            <x-filament::button size="sm" icon="heroicon-o-printer" onclick="window.print()">{{ __('طباعة') }}</x-filament::button>
        </div>
    </x-filament::section>

    <div class="rp-cards">
        <div class="rp-card"><span>{{ __('إيرادات الاشتراكات والمدفوعات') }}</span><b style="color:#16a34a">{{ Money::format($r['payments_total']) }}</b></div>
        <div class="rp-card"><span>{{ __('مبيعات المتجر') }}</span><b style="color:#16a34a">{{ Money::format($r['sales_total']) }}</b><small>{{ __('ربح:') }} {{ Money::format($r['sales_profit']) }}</small></div>
        <div class="rp-card"><span>{{ __('المصروفات') }}</span><b style="color:#dc2626">{{ Money::format($r['expenses_total']) }}</b></div>
        <div class="rp-card"><span>{{ __('صافي الربح') }}</span><b style="color:{{ $r['net'] >= 0 ? '#16a34a' : '#dc2626' }}">{{ Money::format($r['net']) }}</b></div>
        <div class="rp-card"><span>{{ __('مديونيات على المشتركين (إجمالي)') }}</span><b style="color:#d97706">{{ Money::format($r['outstanding']) }}</b></div>
        <div class="rp-card"><span>{{ __('مشتركين جدد') }}</span><b>{{ $r['new_members'] }}</b></div>
        <div class="rp-card"><span>{{ __('اشتراكات مباعة') }}</span><b>{{ $r['new_subs'] }}</b></div>
        <div class="rp-card"><span>{{ __('مرات الحضور') }}</span><b>{{ $r['attendance'] }}</b><small>{{ $r['unique_visitors'] }} {{ __('مشترك مختلف') }}</small><br><small>{{ __('ساعة الذروة:') }} {{ $r['peak_hour'] }}</small></div>
    </div>

    <div class="rp-2">
        <x-filament::section :heading="__('الإيرادات حسب طريقة الدفع')">
            <table class="rp-t">
                <tr><th>{{ __('الطريقة') }}</th><th>{{ __('عدد العمليات') }}</th><th>{{ __('المبلغ') }}</th></tr>
                @forelse ($r['by_method'] as $m)
                    <tr><td>{{ Payment::methods()[$m->method] ?? $m->method }}</td><td>{{ $m->cnt }}</td><td>{{ Money::format($m->total) }}</td></tr>
                @empty
                    <tr><td colspan="3">{{ __('لا توجد بيانات') }}</td></tr>
                @endforelse
            </table>
            <table class="rp-t" style="margin-top:1rem">
                <tr><th>{{ __('نوع الإيراد') }}</th><th>{{ __('المبلغ') }}</th></tr>
                @foreach ($r['by_type'] as $type => $total)
                    <tr><td>{{ Payment::types()[$type] ?? $type }}</td><td>{{ Money::format($total) }}</td></tr>
                @endforeach
            </table>
        </x-filament::section>

        <x-filament::section :heading="__('المصروفات حسب البند')">
            <table class="rp-t">
                <tr><th>{{ __('البند') }}</th><th>{{ __('المبلغ') }}</th></tr>
                @forelse ($r['exp_by_cat'] as $e)
                    <tr><td>{{ $e->name }}</td><td>{{ Money::format($e->total) }}</td></tr>
                @empty
                    <tr><td colspan="2">{{ __('لا توجد مصروفات') }}</td></tr>
                @endforelse
            </table>
        </x-filament::section>

        <x-filament::section :heading="__('الاشتراكات المباعة حسب الباقة')">
            <table class="rp-t">
                <tr><th>{{ __('الباقة') }}</th><th>{{ __('العدد') }}</th><th>{{ __('القيمة') }}</th></tr>
                @forelse ($r['by_plan'] as $p)
                    <tr><td>{{ $p->name }}</td><td>{{ $p->cnt }}</td><td>{{ Money::format($p->total) }}</td></tr>
                @empty
                    <tr><td colspan="3">{{ __('لا توجد بيانات') }}</td></tr>
                @endforelse
            </table>
        </x-filament::section>

        <x-filament::section :heading="__('المدربين: العمولات والمرتبات')">
            <table class="rp-t">
                <tr><th>{{ __('المدرب') }}</th><th>{{ __('قيمة اشتراكاته') }}</th><th>{{ __('العمولة') }}</th><th>{{ __('المرتب') }}</th></tr>
                @forelse ($r['trainers'] as $t)
                    <tr><td>{{ $t['name'] }}</td><td>{{ Money::format($t['subs']) }}</td><td>{{ Money::format($t['commission']) }}</td><td>{{ Money::format($t['salary']) }}</td></tr>
                @empty
                    <tr><td colspan="4">{{ __('لا توجد بيانات') }}</td></tr>
                @endforelse
            </table>
        </x-filament::section>
    </div>
</x-filament-panels::page>
