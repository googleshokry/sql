<x-filament-panels::page>
    @php
        $colors = ['trial' => '#2563eb', 'active' => '#16a34a', 'expired' => '#d97706', 'suspended' => '#dc2626'];
        $usageLabels = ['users' => __('المستخدمين'), 'drivers' => __('الطيارين'), 'products' => __('الأصناف'), 'orders_per_month' => __('طلبات الشهر')];
        $statusLabels = ['pending' => __('قيد المراجعة'), 'paid' => __('مدفوع'), 'failed' => __('فشل')];
    @endphp
    <style>
        .bl-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; }
        .bl-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 14px; padding: 18px; }
        .dark .bl-card { background: #18181b; border-color: #3f3f46; }
        .bl-muted { color: #6b7280; font-size: 13px; }
        .bl-big { font-size: 26px; font-weight: 800; }
        .bl-bar { height: 8px; background: #f3f4f6; border-radius: 99px; overflow: hidden; margin-top: 6px; }
        .dark .bl-bar { background: #27272a; }
        .bl-bar > div { height: 100%; background: #f97316; }
        .bl-badge { display: inline-block; padding: 2px 10px; border-radius: 99px; color: #fff; font-size: 12px; font-weight: 700; }
        .bl-plan.current { border: 2px solid #f97316; }
        .bl-plan select { width: 100%; padding: 8px; border-radius: 8px; border: 1px solid #d1d5db; margin: 10px 0; background: transparent; color: inherit; }
        .bl-btn { width: 100%; padding: 10px; border-radius: 10px; border: 0; background: #f97316; color: #fff; font-weight: 700; cursor: pointer; }
        .bl-alert { padding: 12px 14px; border-radius: 10px; font-weight: 600; }
        table.bl { width: 100%; border-collapse: collapse; font-size: 14px; }
        table.bl th, table.bl td { padding: 8px; border-bottom: 1px solid #e5e7eb; text-align: start; }
        .dark table.bl th, .dark table.bl td { border-color: #3f3f46; }
    </style>

    @if ($flash === 'paid')
        <div class="bl-alert" style="background:#dcfce7;color:#166534">✅ {{ __('تم الدفع وتجديد الاشتراك. شكراً!') }}</div>
    @elseif ($flash === 'failed')
        <div class="bl-alert" style="background:#fee2e2;color:#991b1b">{{ __('عملية الدفع لم تتم. حاول مرة أخرى.') }}</div>
    @elseif ($flash === 'pending')
        <div class="bl-alert" style="background:#fef3c7;color:#92400e">{{ __('الدفع قيد المعالجة، هيتحدث الاشتراك تلقائياً أول ما يتأكد.') }}</div>
    @endif
    @if (session('billing_error'))
        <div class="bl-alert" style="background:#fee2e2;color:#991b1b">{{ session('billing_error') }}</div>
    @endif
    @if (in_array($state, ['expired', 'suspended']))
        <div class="bl-alert" style="background:#fee2e2;color:#991b1b">
            {{ $state === 'expired' ? __('انتهى اشتراك المطعم. جدد الاشتراك عشان تكمل.') : __('حساب المطعم موقوف. تواصل مع إدارة المنصة.') }}
        </div>
    @endif

    <div class="bl-grid">
        <div class="bl-card">
            <div class="bl-muted">{{ __('الباقة الحالية') }}</div>
            <div class="bl-big">{{ $tenant->plan?->localized_name ?? '-' }}</div>
            <span class="bl-badge" style="background: {{ $colors[$state] }}">{{ \App\Models\Central\Tenant::stateLabel($state) }}</span>
        </div>
        <div class="bl-card">
            <div class="bl-muted">{{ $state === 'trial' ? __('التجربة تنتهي') : __('الاشتراك ينتهي') }}</div>
            <div class="bl-big">{{ $tenant->endsAt()?->format('Y-m-d') ?? '-' }}</div>
            <div class="bl-muted">{{ __('متبقي :days يوم', ['days' => $tenant->daysLeft()]) }}</div>
        </div>
        @foreach ($usage as $key => $u)
            <div class="bl-card">
                <div class="bl-muted">{{ $usageLabels[$key] }}</div>
                <div class="bl-big">{{ $u['used'] }} <span class="bl-muted">/ {{ $u['limit'] ?? '∞' }}</span></div>
                @if ($u['limit'])
                    <div class="bl-bar"><div style="width: {{ min(100, round($u['used'] / max(1, $u['limit']) * 100)) }}%; {{ $u['used'] >= $u['limit'] ? 'background:#dc2626' : '' }}"></div></div>
                @endif
            </div>
        @endforeach
    </div>

    <h2 style="font-size:18px;font-weight:700;margin-top:8px">{{ __('جدد أو رقّي الباقة') }}</h2>
    @unless ($paymob)
        <div class="bl-muted">{{ __('الدفع الأونلاين غير مفعل حالياً. استخدم زر "أبلغ عن دفع يدوي" فوق.') }} {{ $instructions }}</div>
    @endunless
    <div class="bl-grid">
        @foreach ($plans as $plan)
            <div class="bl-card bl-plan {{ $plan->id === $tenant->plan_id ? 'current' : '' }}">
                <div style="font-weight:800;font-size:18px">{{ $plan->localized_name }}</div>
                <div class="bl-big">{{ number_format($plan->price_monthly) }} <span class="bl-muted">{{ __('ج.م / شهر') }}</span></div>
                <div class="bl-muted">
                    {{ $plan->max_users ?? '∞' }} {{ __('مستخدمين') }} · {{ $plan->max_drivers ?? '∞' }} {{ __('طيارين') }} ·
                    {{ $plan->max_orders_per_month ? number_format($plan->max_orders_per_month) : '∞' }} {{ __('طلب/شهر') }}
                </div>
                @if ($paymob)
                    <form method="POST" action="{{ route('billing.paymob') }}">
                        @csrf
                        <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                        <select name="months">
                            <option value="1">{{ __('شهر') }} — {{ number_format($plan->priceFor(1)) }}</option>
                            <option value="3">{{ __('3 شهور') }} — {{ number_format($plan->priceFor(3)) }}</option>
                            <option value="6">{{ __('6 شهور') }} — {{ number_format($plan->priceFor(6)) }}</option>
                            <option value="12">{{ __('سنة') }} — {{ number_format($plan->priceFor(12)) }}</option>
                        </select>
                        <button class="bl-btn" type="submit">💳 {{ __('ادفع أونلاين') }}</button>
                    </form>
                @endif
            </div>
        @endforeach
    </div>

    @if ($payments->isNotEmpty())
        <h2 style="font-size:18px;font-weight:700;margin-top:8px">{{ __('سجل المدفوعات') }}</h2>
        <div class="bl-card" style="overflow-x:auto">
            <table class="bl">
                <thead><tr><th>{{ __('التاريخ') }}</th><th>{{ __('الباقة') }}</th><th>{{ __('المدة') }}</th><th>{{ __('المبلغ') }}</th><th>{{ __('طريقة الدفع') }}</th><th>{{ __('الحالة') }}</th></tr></thead>
                <tbody>
                @foreach ($payments as $p)
                    <tr>
                        <td>{{ $p->created_at->format('Y-m-d') }}</td>
                        <td>{{ $p->plan?->localized_name }}</td>
                        <td>{{ $p->months }}</td>
                        <td>{{ number_format($p->amount, 2) }}</td>
                        <td>{{ \App\Models\Central\Payment::methodLabel($p->method) }}</td>
                        <td>{{ $statusLabels[$p->status] ?? $p->status }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-filament-panels::page>
