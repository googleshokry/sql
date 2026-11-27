@extends('saas.layout')

@push('styles')
<style>
    .hero { display: grid; grid-template-columns: 1.2fr 1fr; gap: 32px; align-items: center; padding: 40px 0 24px; }
    @media (max-width: 860px) { .hero { grid-template-columns: 1fr; } }
    .hero h1 { font-size: clamp(28px, 5vw, 44px); line-height: 1.25; margin: 0 0 12px; }
    .hero p { color: var(--muted); font-size: 18px; margin: 0 0 24px; }
    .find { display: flex; gap: 8px; margin-top: 8px; }
    .find input { flex: 1; }
    .features { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 16px; margin: 32px 0; }
    .feature .ic { font-size: 28px; }
    .feature h3 { margin: 8px 0 4px; font-size: 17px; }
    .feature p { margin: 0; color: var(--muted); font-size: 14px; }
    h2 { font-size: 28px; text-align: center; margin: 48px 0 8px; }
    .sub { text-align: center; color: var(--muted); margin: 0 0 24px; }
    .plans { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; }
    .plan.featured { border: 2px solid var(--brand); }
    .price { font-size: 34px; font-weight: 800; margin: 8px 0 0; }
    .price small { font-size: 14px; color: var(--muted); font-weight: 400; }
    .plan ul { list-style: none; padding: 0; margin: 16px 0 20px; }
    .plan li { padding: 5px 0; border-bottom: 1px dashed var(--line); font-size: 14px; }
    .plan li::before { content: "✓ "; color: var(--brand); font-weight: 700; }
    .mock { background: linear-gradient(135deg, #fed7aa, #f97316); border-radius: 20px; padding: 24px; color: #fff; min-height: 260px; display: flex; flex-direction: column; justify-content: center; gap: 10px; }
    .mock div { background: rgba(255,255,255,.18); border-radius: 12px; padding: 12px 14px; font-weight: 700; }
</style>
@endpush

@section('nav')
    <a class="btn btn-primary" href="{{ route('signup') }}">{{ __('جرب مجاناً') }}</a>
@endsection

@section('content')
    <section class="hero">
        <div>
            <h1>{{ __('نظام كاشير ودليفري متكامل لمطعمك') }}</h1>
            <p>{{ __('صالة وتيك أواي ودليفري، تطبيق للطيارين بتتبع الموقع، تطبيق لعملائك، وتقارير لحظية — كله أونلاين من غير سيرفرات.') }}</p>
            <a class="btn btn-primary" href="{{ route('signup') }}">{{ __('ابدأ تجربة مجانية :days يوم', ['days' => $trialDays]) }}</a>
            <form class="find" action="{{ route('tenant.find') }}" method="GET">
                <input name="code" value="{{ old('code') }}" placeholder="{{ __('عندك حساب؟ اكتب كود المطعم') }}" dir="ltr" required>
                <button class="btn btn-ghost" type="submit">{{ __('دخول') }}</button>
            </form>
            @error('code')<div class="error">{{ $message }}</div>@enderror
        </div>
        <div class="mock" aria-hidden="true">
            <div>🧾 {{ __('طلب #0142 — دليفري — 225 ج.م') }}</div>
            <div>🛵 {{ __('أحمد: في الطريق للعميل') }}</div>
            <div>📈 {{ __('مبيعات اليوم: 18,450 ج.م') }}</div>
        </div>
    </section>

    <section class="features">
        @foreach ([
            ['🧾', __('كاشير سريع'), __('صالة وطاولات، تيك أواي، دليفري، خصومات، فواتير ومطبخ.')],
            ['🛵', __('تطبيق الطيار'), __('تتبع الموقع لحظياً، مراحل التوصيل، وتحصيل الكاش.')],
            ['📱', __('تطبيق العملاء'), __('عملائك يطلبوا ويتابعوا الطلب على الخريطة.')],
            ['📊', __('تقارير المالك'), __('مبيعات وأرباح وأكثر الأصناف مبيعاً من موبايلك.')],
            ['🌐', __('عربي وإنجليزي'), __('النظام والتطبيقات باللغتين.')],
            ['🔒', __('بيانات منفصلة'), __('كل مطعم بقاعدة بيانات خاصة بيه.')],
        ] as [$icon, $title, $text])
            <div class="card feature"><div class="ic">{{ $icon }}</div><h3>{{ $title }}</h3><p>{{ $text }}</p></div>
        @endforeach
    </section>

    <h2>{{ __('الباقات') }}</h2>
    <p class="sub">{{ __('كل الباقات تبدأ بتجربة مجانية :days يوم — من غير كارت', ['days' => $trialDays]) }}</p>
    <section class="plans">
        @foreach ($plans as $i => $plan)
            <div class="card plan {{ $i === 1 ? 'featured' : '' }}">
                <h3 style="margin:0">{{ $plan->localized_name }}</h3>
                <div class="price">{{ number_format($plan->price_monthly) }} <small>{{ __('ج.م / شهر') }}</small></div>
                @if ($plan->price_yearly > 0)
                    <div class="hint">{{ __('أو :price ج.م سنوياً', ['price' => number_format($plan->price_yearly)]) }}</div>
                @endif
                <ul>
                    <li>{{ $plan->max_users ? __(':n مستخدمين', ['n' => $plan->max_users]) : __('مستخدمين بلا حدود') }}</li>
                    <li>{{ $plan->max_drivers ? __(':n طيارين', ['n' => $plan->max_drivers]) : __('طيارين بلا حدود') }}</li>
                    <li>{{ $plan->max_products ? __(':n صنف', ['n' => $plan->max_products]) : __('أصناف بلا حدود') }}</li>
                    <li>{{ $plan->max_orders_per_month ? __(':n طلب شهرياً', ['n' => number_format($plan->max_orders_per_month)]) : __('طلبات بلا حدود') }}</li>
                    <li>{{ $plan->customer_app ? __('تطبيق العملاء') : __('بدون تطبيق العملاء') }}</li>
                </ul>
                <a class="btn {{ $i === 1 ? 'btn-primary' : 'btn-ghost' }}" style="width:100%;text-align:center" href="{{ route('signup', ['plan' => $plan->id]) }}">{{ __('ابدأ مجاناً') }}</a>
            </div>
        @endforeach
    </section>
@endsection
