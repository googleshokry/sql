@php($company = auth()->check() ? \App\Support\Tenancy::company() : null)
@if($company && ($company->status === 'trial' || ! $company->hasActiveSubscription() || ($company->daysLeft() !== null && $company->daysLeft() <= 7)))
    @php($expired = ! $company->hasActiveSubscription())
    <div style="background:{{ $expired ? 'rgb(220,38,38)' : 'rgb(79,70,229)' }};color:#fff;text-align:center;padding:.45rem 1rem;font-size:.875rem;font-weight:600">
        @if($expired)
            {{ __('انتهى اشتراك الشركة. برجاء تجديد الاشتراك.') }}
        @elseif($company->status === 'trial')
            {{ __('أنت في الفترة التجريبية — متبقي :days يوم', ['days' => $company->daysLeft()]) }}
        @else
            {{ __('اشتراكك ينتهي خلال :days يوم', ['days' => $company->daysLeft()]) }}
        @endif
        — <a href="{{ \App\Filament\Pages\Subscription::getUrl() }}" style="text-decoration:underline;color:#fff">{{ __('الاشتراك والباقة') }}</a>
    </div>
@endif
