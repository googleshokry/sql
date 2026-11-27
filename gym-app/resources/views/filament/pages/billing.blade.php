@php
    $gym = $this->gym;
    $usage = $this->usage;
    $plan = $gym->saasPlan;
@endphp
<x-filament-panels::page>
    <style>
        .bl-grid { display:grid; gap:1rem; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); }
        .bl-box { padding:1rem; border-radius:.75rem; background:rgba(127,127,127,.07); }
        .bl-box span { font-size:.85rem; opacity:.75; display:block } .bl-box b { font-size:1.3rem; }
        .bl-plan { border:1px solid rgba(127,127,127,.25); border-radius:1rem; padding:1.25rem; display:flex; flex-direction:column; gap:.5rem; }
        .bl-plan.current { border:2px solid #f59e0b; }
        .bl-price { font-size:1.75rem; font-weight:800; }
        .bl-t { width:100%; border-collapse:collapse } .bl-t td, .bl-t th { padding:.5rem; border-bottom:1px solid rgba(127,127,127,.15); text-align:start }
    </style>

    @unless ($gym->hasAccess())
        <div style="padding:1rem;border-radius:.75rem;background:#fee2e2;color:#991b1b;font-weight:700">
            {{ $gym->status === 'suspended' ? __('تم إيقاف حساب الجيم: :reason', ['reason' => $gym->suspended_reason ?: '-']) : __('انتهت الفترة التجريبية أو الاشتراك. اختر باقة وادفع الفاتورة لإعادة التفعيل.') }}
        </div>
    @endunless

    <div class="bl-grid">
        <div class="bl-box"><span>{{ __('الحالة') }}</span><b><x-filament::badge :color="\App\Models\Gym::STATUS_COLORS[$gym->status] ?? 'gray'">{{ \App\Models\Gym::statuses()[$gym->status] ?? $gym->status }}</x-filament::badge></b></div>
        <div class="bl-box"><span>{{ __('الباقة') }}</span><b>{{ $plan?->localizedName() ?? '-' }}</b></div>
        <div class="bl-box"><span>{{ $gym->onTrial() ? __('تنتهي التجربة في') : __('ينتهي الاشتراك في') }}</span><b>{{ $gym->accessEndsAt()?->format('Y-m-d') ?? '-' }}</b> <small>({{ __(':days يوم', ['days' => $gym->daysLeft()]) }})</small></div>
        <div class="bl-box"><span>{{ __('المشتركين') }}</span><b>{{ $usage['members'] }} / {{ $plan?->max_members ?? '∞' }}</b></div>
        <div class="bl-box"><span>{{ __('الموظفين') }}</span><b>{{ $usage['staff'] }} / {{ $plan?->max_staff ?? '∞' }}</b></div>
        <div class="bl-box"><span>{{ __('الفروع') }}</span><b>{{ $usage['branches'] }} / {{ $plan?->max_branches ?? 1 }}</b></div>
    </div>

    <x-filament::section :heading="__('الباقات')">
        <div style="display:flex;gap:.5rem;margin-bottom:1rem">
            <x-filament::button size="sm" :color="$cycle === 'monthly' ? 'primary' : 'gray'" wire:click="$set('cycle', 'monthly')">{{ __('شهري') }}</x-filament::button>
            <x-filament::button size="sm" :color="$cycle === 'yearly' ? 'primary' : 'gray'" wire:click="$set('cycle', 'yearly')">{{ __('سنوي') }}</x-filament::button>
        </div>
        <div class="bl-grid">
            @foreach ($this->plans as $p)
                <div class="bl-plan {{ $plan?->id === $p->id ? 'current' : '' }}">
                    <b style="font-size:1.2rem">{{ $p->localizedName() }}</b>
                    <div class="bl-price">{{ number_format($p->priceFor($cycle)) }} <small style="font-size:.9rem">{{ $p->currency }} / {{ $cycle === 'yearly' ? __('سنوياً') : __('شهرياً') }}</small></div>
                    <div>👥 {{ $p->max_members ? __(':count مشترك', ['count' => number_format($p->max_members)]) : __('مشتركين بلا حدود') }}</div>
                    <div>🧑‍💼 {{ $p->max_staff ? __(':count موظف', ['count' => $p->max_staff]) : __('موظفين بلا حدود') }}</div>
                    <div>🏢 {{ __(':count فرع', ['count' => $p->max_branches]) }}</div>
                    <div>📱 {{ $p->mobile_app ? __('تطبيق موبايل للمشتركين') : __('بدون تطبيق موبايل') }}</div>
                    @if ($p->description)<div style="opacity:.75">{{ $p->description }}</div>@endif
                    @if (auth()->user()->isAdmin())
                        <x-filament::button wire:click="choose({{ $p->id }})" wire:confirm="{{ __('إصدار فاتورة لهذه الباقة؟') }}">{{ __('اشترك في هذه الباقة') }}</x-filament::button>
                    @endif
                </div>
            @endforeach
        </div>
    </x-filament::section>

    <x-filament::section :heading="__('طريقة الدفع')">
        <p>{{ config('saas.payment_instructions') }}</p>
        <p style="opacity:.75">{{ __('للمساعدة:') }} {{ config('saas.support_phone') }} — {{ config('saas.support_email') }}</p>
    </x-filament::section>

    <x-filament::section :heading="__('الفواتير')">
        <table class="bl-t">
            <tr><th>{{ __('رقم الفاتورة') }}</th><th>{{ __('الفترة') }}</th><th>{{ __('المبلغ') }}</th><th>{{ __('الحالة') }}</th></tr>
            @forelse ($this->invoices as $inv)
                <tr>
                    <td>{{ $inv->number }}</td>
                    <td>{{ $inv->period_start->format('Y-m-d') }} ← {{ $inv->period_end->format('Y-m-d') }}</td>
                    <td>{{ number_format((float) $inv->amount, 2) }} {{ $inv->currency }}</td>
                    <td><x-filament::badge :color="['paid' => 'success', 'unpaid' => 'warning', 'void' => 'gray'][$inv->status] ?? 'gray'">{{ \App\Models\GymInvoice::statuses()[$inv->status] ?? $inv->status }}</x-filament::badge></td>
                </tr>
            @empty
                <tr><td colspan="4">{{ __('لا توجد فواتير') }}</td></tr>
            @endforelse
        </table>
    </x-filament::section>
</x-filament-panels::page>
