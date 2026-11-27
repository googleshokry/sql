<x-filament-panels::page>
    @php($active = $company->hasActiveSubscription())
    @if(! $active)
        <div style="border:1px solid rgb(220,38,38);background:rgba(220,38,38,.08);border-radius:.75rem;padding:1rem;font-weight:700" class="text-danger">
            {{ __('انتهى اشتراك الشركة. برجاء تجديد الاشتراك.') }}
        </div>
    @endif

    <x-filament::section>
        <div class="erp-kpis">
            <div class="erp-kpi"><div>{{ __('الشركة') }}</div><div class="v">{{ $company->name }}</div></div>
            <div class="erp-kpi"><div>{{ __('الباقة') }}</div><div class="v">{{ $company->plan?->displayName() ?? '-' }}</div></div>
            <div class="erp-kpi"><div>{{ __('الحالة') }}</div><div class="v {{ $active ? 'text-success' : 'text-danger' }}">{{ __(\App\Models\Company::STATUSES[$company->status] ?? $company->status) }}</div></div>
            <div class="erp-kpi">
                <div>{{ __('ينتهي في') }}</div>
                <div class="v">{{ $company->accessEndsAt()?->format('Y-m-d') ?? __('غير محدد') }}</div>
                @if($company->daysLeft() !== null)<div style="font-size:.85rem;opacity:.75">{{ __('متبقي :days يوم', ['days' => $company->daysLeft()]) }}</div>@endif
            </div>
        </div>

        <table class="erp-table" style="max-width:640px">
            <thead><tr><th>{{ __('الاستخدام') }}</th><th class="num">{{ __('الحالي') }}</th><th class="num">{{ __('الحد الأقصى') }}</th></tr></thead>
            <tbody>
                @foreach(['users' => 'المستخدمين', 'branches' => 'الفروع', 'warehouses' => 'المخازن'] as $key => $label)
                    <tr>
                        <td>{{ __($label) }}</td>
                        <td class="num">{{ $usage[$key][0] }}</td>
                        <td class="num">{{ $usage[$key][1] ?: __('غير محدود') }}</td>
                    </tr>
                @endforeach
                <tr><td>{{ __('فواتير هذا الشهر') }}</td><td class="num">{{ $invoicesThisMonth }}</td><td class="num">{{ __('غير محدود') }}</td></tr>
            </tbody>
        </table>
    </x-filament::section>

    <x-filament::section :heading="__('الباقات المتاحة')">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1rem">
            @foreach($plans as $plan)
                @php($current = $company->plan_id === $plan->id)
                <div class="erp-kpi" style="{{ $current ? 'border:2px solid rgb(79,70,229)' : '' }}">
                    <div style="font-size:1.15rem;font-weight:800">{{ $plan->displayName() }} @if($current)<span style="font-size:.75rem;color:rgb(79,70,229)">({{ __('باقتك الحالية') }})</span>@endif</div>
                    <div class="v">{{ number_format($plan->monthly_price, 0) }} <span style="font-size:.8rem;font-weight:400">{{ \App\Filament\Support\Money::currency() }} / {{ __('شهرياً') }}</span></div>
                    <div style="font-size:.85rem;opacity:.75">{{ number_format($plan->yearly_price, 0) }} {{ __('سنوياً') }}</div>
                    <ul style="margin-top:.75rem;font-size:.85rem;line-height:1.9">
                        <li>👤 {{ __('المستخدمين') }}: {{ $plan->max_users ?: __('غير محدود') }}</li>
                        <li>🏢 {{ __('الفروع') }}: {{ $plan->max_branches ?: __('غير محدود') }}</li>
                        <li>📦 {{ __('المخازن') }}: {{ $plan->max_warehouses ?: __('غير محدود') }}</li>
                        @foreach(\App\Models\Plan::FEATURES as $feature => $label)
                            <li style="{{ $plan->hasFeature($feature) ? '' : 'opacity:.4;text-decoration:line-through' }}">{{ $plan->hasFeature($feature) ? '✔' : '✘' }} {{ __($label) }}</li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
        <div style="margin-top:1rem">
            {{ __('للترقية أو التجديد تواصل معنا:') }}
            <a href="mailto:{{ $supportEmail }}" style="color:rgb(79,70,229)">{{ $supportEmail }}</a>
            @if($supportWhatsapp) — <a href="https://wa.me/{{ preg_replace('/\D/', '', $supportWhatsapp) }}" target="_blank" style="color:rgb(79,70,229)">WhatsApp {{ $supportWhatsapp }}</a>@endif
        </div>
    </x-filament::section>
</x-filament-panels::page>
