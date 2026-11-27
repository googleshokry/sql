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
            <label>من <input type="date" wire:model.live="from"></label>
            <label>إلى <input type="date" wire:model.live="until"></label>
            @foreach (['today' => 'اليوم', 'yesterday' => 'أمس', 'week' => 'هذا الأسبوع', 'month' => 'هذا الشهر', 'last_month' => 'الشهر الماضي', 'year' => 'هذه السنة'] as $k => $label)
                <x-filament::button size="sm" color="gray" wire:click="preset('{{ $k }}')">{{ $label }}</x-filament::button>
            @endforeach
            <x-filament::button size="sm" icon="heroicon-o-printer" onclick="window.print()">طباعة</x-filament::button>
        </div>
    </x-filament::section>

    <div class="rp-cards">
        <div class="rp-card"><span>إيرادات الاشتراكات والمدفوعات</span><b style="color:#16a34a">{{ Money::format($r['payments_total']) }}</b></div>
        <div class="rp-card"><span>مبيعات المتجر</span><b style="color:#16a34a">{{ Money::format($r['sales_total']) }}</b><small>ربح: {{ Money::format($r['sales_profit']) }}</small></div>
        <div class="rp-card"><span>المصروفات</span><b style="color:#dc2626">{{ Money::format($r['expenses_total']) }}</b></div>
        <div class="rp-card"><span>صافي الربح</span><b style="color:{{ $r['net'] >= 0 ? '#16a34a' : '#dc2626' }}">{{ Money::format($r['net']) }}</b></div>
        <div class="rp-card"><span>مديونيات على المشتركين (إجمالي)</span><b style="color:#d97706">{{ Money::format($r['outstanding']) }}</b></div>
        <div class="rp-card"><span>مشتركين جدد</span><b>{{ $r['new_members'] }}</b></div>
        <div class="rp-card"><span>اشتراكات مباعة</span><b>{{ $r['new_subs'] }}</b></div>
        <div class="rp-card"><span>مرات الحضور</span><b>{{ $r['attendance'] }}</b><small>{{ $r['unique_visitors'] }} مشترك مختلف</small><br><small>ساعة الذروة: {{ $r['peak_hour'] }}</small></div>
    </div>

    <div class="rp-2">
        <x-filament::section heading="الإيرادات حسب طريقة الدفع">
            <table class="rp-t">
                <tr><th>الطريقة</th><th>عدد العمليات</th><th>المبلغ</th></tr>
                @forelse ($r['by_method'] as $m)
                    <tr><td>{{ Payment::METHODS[$m->method] ?? $m->method }}</td><td>{{ $m->cnt }}</td><td>{{ Money::format($m->total) }}</td></tr>
                @empty
                    <tr><td colspan="3">لا توجد بيانات</td></tr>
                @endforelse
            </table>
            <table class="rp-t" style="margin-top:1rem">
                <tr><th>نوع الإيراد</th><th>المبلغ</th></tr>
                @foreach ($r['by_type'] as $type => $total)
                    <tr><td>{{ Payment::TYPES[$type] ?? $type }}</td><td>{{ Money::format($total) }}</td></tr>
                @endforeach
            </table>
        </x-filament::section>

        <x-filament::section heading="المصروفات حسب البند">
            <table class="rp-t">
                <tr><th>البند</th><th>المبلغ</th></tr>
                @forelse ($r['exp_by_cat'] as $e)
                    <tr><td>{{ $e->name }}</td><td>{{ Money::format($e->total) }}</td></tr>
                @empty
                    <tr><td colspan="2">لا توجد مصروفات</td></tr>
                @endforelse
            </table>
        </x-filament::section>

        <x-filament::section heading="الاشتراكات المباعة حسب الباقة">
            <table class="rp-t">
                <tr><th>الباقة</th><th>العدد</th><th>القيمة</th></tr>
                @forelse ($r['by_plan'] as $p)
                    <tr><td>{{ $p->name }}</td><td>{{ $p->cnt }}</td><td>{{ Money::format($p->total) }}</td></tr>
                @empty
                    <tr><td colspan="3">لا توجد بيانات</td></tr>
                @endforelse
            </table>
        </x-filament::section>

        <x-filament::section heading="المدربين: العمولات والمرتبات">
            <table class="rp-t">
                <tr><th>المدرب</th><th>قيمة اشتراكاته</th><th>العمولة</th><th>المرتب</th></tr>
                @forelse ($r['trainers'] as $t)
                    <tr><td>{{ $t['name'] }}</td><td>{{ Money::format($t['subs']) }}</td><td>{{ Money::format($t['commission']) }}</td><td>{{ Money::format($t['salary']) }}</td></tr>
                @empty
                    <tr><td colspan="4">لا توجد بيانات</td></tr>
                @endforelse
            </table>
        </x-filament::section>
    </div>
</x-filament-panels::page>
