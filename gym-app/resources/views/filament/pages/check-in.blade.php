@php
    use App\Models\Subscription;
    use App\Support\Money;
    $member = $this->member;
    $sub = $this->subscription;
@endphp
<x-filament-panels::page>
    <style>
        .ci-grid { display:grid; grid-template-columns: 1fr; gap: 1.5rem; }
        @media (min-width: 1024px) { .ci-grid { grid-template-columns: 2fr 1fr; } }
        .ci-input { width:100%; font-size:1.4rem; padding:1rem 1.25rem; border-radius:.75rem; border:2px solid rgb(var(--primary-500, 245 158 11)); background:transparent; text-align:center; letter-spacing:.1em; }
        .ci-input:focus { outline:none; box-shadow:0 0 0 4px rgba(245,158,11,.25); }
        .ci-alert { padding:1rem 1.25rem; border-radius:.75rem; font-size:1.25rem; font-weight:700; text-align:center; margin-top:1rem; }
        .ci-ok { background:#dcfce7; color:#166534; } .ci-out { background:#e0f2fe; color:#075985; } .ci-err { background:#fee2e2; color:#991b1b; }
        .ci-card { display:flex; gap:1.25rem; align-items:center; flex-wrap:wrap; }
        .ci-card img { width:120px; height:120px; border-radius:9999px; object-fit:cover; border:4px solid #f59e0b; }
        .ci-stats { display:grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap:.75rem; margin-top:1rem; }
        .ci-stat { padding:.75rem; border-radius:.5rem; background:rgba(127,127,127,.08); text-align:center; }
        .ci-stat b { display:block; font-size:1.2rem; }
        .ci-list { display:flex; flex-direction:column; gap:.5rem; max-height:520px; overflow:auto; }
        .ci-row { display:flex; justify-content:space-between; align-items:center; padding:.5rem .75rem; border-radius:.5rem; background:rgba(127,127,127,.06); }
        .ci-match { cursor:pointer; } .ci-match:hover { background:rgba(245,158,11,.15); }
    </style>

    <div class="ci-grid">
        <div>
            <x-filament::section>
                <form wire:submit="submit">
                    <input type="text" wire:model="search" class="ci-input" autofocus autocomplete="off"
                           placeholder="{{ __('امسح الكارت أو اكتب الكود / الموبايل / الاسم') }}" x-data
                           x-init="$el.focus()" x-on:checkin-result.window="$el.focus()">
                </form>

                @if ($result)
                    <div class="ci-alert {{ ! $result['ok'] ? 'ci-err' : ($result['action'] === 'out' ? 'ci-out' : 'ci-ok') }}">
                        {{ $result['ok'] ? ($result['action'] === 'out' ? '👋 ' : '✅ ') : '⛔ ' }}{{ $result['message'] }}
                    </div>
                    @if (! $result['ok'] && $member && auth()->user()->isManager())
                        <div style="text-align:center;margin-top:.75rem">
                            <x-filament::button color="danger" wire:click="force" size="sm">{{ __('السماح بالدخول استثنائياً') }}</x-filament::button>
                        </div>
                    @endif
                @endif

                @if ($matches)
                    <div class="ci-list" style="margin-top:1rem">
                        <div style="font-weight:700">{{ __('اختر المشترك:') }}</div>
                        @foreach ($matches as $m)
                            <div class="ci-row ci-match" wire:click="select({{ $m['id'] }})">
                                <span><b>{{ $m['name'] }}</b> — {{ $m['phone'] }}</span>
                                <x-filament::badge>{{ $m['code'] }}</x-filament::badge>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-filament::section>

            @if ($member)
                <x-filament::section style="margin-top:1.5rem">
                    <div class="ci-card">
                        <img src="{{ $member->photo_url }}" alt="">
                        <div style="flex:1">
                            <div style="font-size:1.5rem;font-weight:800">{{ $member->name }}</div>
                            <div style="opacity:.75">{{ __('كود:') }} {{ $member->code }} • {{ $member->phone }}</div>
                            <div style="margin-top:.5rem;display:flex;gap:.5rem;flex-wrap:wrap">
                                @if ($sub)
                                    <x-filament::badge :color="Subscription::STATUS_COLORS[$sub->status] ?? 'gray'">
                                        {{ Subscription::statuses()[$sub->status] ?? $sub->status }}
                                    </x-filament::badge>
                                @else
                                    <x-filament::badge color="gray">{{ __('بدون اشتراك') }}</x-filament::badge>
                                @endif
                                @if ($member->is_blocked)
                                    <x-filament::badge color="danger">{{ __('محظور') }}</x-filament::badge>
                                @endif
                                @if ($member->birth_date && $member->birth_date->isBirthday())
                                    <x-filament::badge color="success">{{ __('🎂 عيد ميلاد سعيد') }}</x-filament::badge>
                                @endif
                            </div>
                        </div>
                        <x-filament::button tag="a" :href="\App\Filament\Resources\MemberResource::getUrl('view', ['record' => $member])" color="gray" size="sm">
                            {{ __('ملف المشترك') }}
                        </x-filament::button>
                    </div>

                    @if ($sub)
                        <div class="ci-stats">
                            <div class="ci-stat">{{ __('الباقة') }}<b>{{ $sub->plan?->name }}</b></div>
                            <div class="ci-stat">{{ __('ينتهي') }}<b>{{ $sub->end_date->format('Y-m-d') }}</b></div>
                            <div class="ci-stat">{{ __('الأيام المتبقية') }}<b style="color:{{ $sub->days_left <= 7 ? '#dc2626' : 'inherit' }}">{{ $sub->days_left }}</b></div>
                            <div class="ci-stat">{{ __('الحصص') }}<b>{{ $sub->sessions_total ? $sub->sessions_used.' / '.$sub->sessions_total : __('غير محدود') }}</b></div>
                            <div class="ci-stat">{{ __('المتبقي عليه') }}<b style="color:{{ $sub->remaining_amount > 0 ? '#dc2626' : '#16a34a' }}">{{ Money::format($sub->remaining_amount) }}</b></div>
                            <div class="ci-stat">{{ __('الدعوات') }}<b>{{ $sub->guest_invitations - $sub->guest_invitations_used }} / {{ $sub->guest_invitations }}</b></div>
                        </div>
                    @endif
                    @if ($member->medical_notes)
                        <div class="ci-alert ci-err" style="font-size:1rem">{{ __('⚠️ ملاحظة طبية:') }} {{ $member->medical_notes }}</div>
                    @endif
                </x-filament::section>
            @endif
        </div>

        <x-filament::section>
            <x-slot name="heading">{{ __('المتواجدون الآن (:inside) — حضور اليوم: :today', ['inside' => $this->inside->count(), 'today' => $this->todayCount]) }}</x-slot>
            <div class="ci-list" wire:poll.30s>
                @forelse ($this->inside as $a)
                    <div class="ci-row">
                        <span>
                            <b>{{ $a->member?->name }}</b>
                            <small style="opacity:.7">{{ $a->is_guest ? '('.__('ضيف: :name', ['name' => $a->guest_name]).')' : '' }} — {{ __('دخل :time', ['time' => $a->check_in_at->format('h:i A')]) }}</small>
                        </span>
                        <x-filament::button size="xs" color="gray" wire:click="checkout({{ $a->id }})">{{ __('خروج') }}</x-filament::button>
                    </div>
                @empty
                    <div style="opacity:.6;text-align:center">{{ __('لا يوجد أحد بالداخل') }}</div>
                @endforelse
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
