@php($gym = \Filament\Facades\Filament::getTenant())
@if ($gym instanceof \App\Models\Gym && ($gym->onTrial() || ($gym->hasAccess() && $gym->daysLeft() <= 5)))
    <div style="margin-bottom:1rem;padding:.75rem 1rem;border-radius:.75rem;background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.4);display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap">
        <span style="font-weight:600">
            @if ($gym->onTrial())
                {{ __('أنت في الفترة التجريبية — متبقي :days يوم', ['days' => $gym->daysLeft()]) }}
            @else
                {{ __('اشتراك الجيم ينتهي خلال :days يوم', ['days' => $gym->daysLeft()]) }}
            @endif
        </span>
        <a href="{{ \App\Filament\Pages\Billing::getUrl() }}" style="font-weight:700;color:#b45309;text-decoration:underline">{{ __('اشترك الآن') }}</a>
    </div>
@endif
