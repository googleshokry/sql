@php($rtl = app()->getLocale() === 'ar')
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} — {{ __('نظام إدارة الجيم') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary:#f59e0b; --dark:#111827; --muted:#6b7280; --bg:#f9fafb; --card:#fff; --border:#e5e7eb; }
        * { box-sizing:border-box } body { margin:0; font-family:'Cairo',system-ui,sans-serif; color:var(--dark); background:var(--bg); }
        a { color:inherit; text-decoration:none } .wrap { max-width:1100px; margin:0 auto; padding:0 16px }
        header { display:flex; justify-content:space-between; align-items:center; padding:16px 0; gap:12px; flex-wrap:wrap }
        .logo { font-weight:800; font-size:1.3rem } .nav { display:flex; gap:10px; align-items:center; flex-wrap:wrap }
        .btn { display:inline-block; padding:10px 18px; border-radius:10px; font-weight:700; border:1px solid var(--border); background:var(--card) }
        .btn.primary { background:var(--primary); border-color:var(--primary); color:#fff }
        .hero { padding:56px 0 40px; text-align:center } .hero h1 { font-size:clamp(1.8rem,5vw,3rem); margin:0 0 12px; line-height:1.3 }
        .hero p { color:var(--muted); font-size:1.15rem; max-width:680px; margin:0 auto 24px }
        .grid { display:grid; gap:16px; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)) }
        .card { background:var(--card); border:1px solid var(--border); border-radius:16px; padding:20px }
        .card h3 { margin:6px 0 } .card p { color:var(--muted); margin:0 }
        section { padding:32px 0 } h2 { text-align:center; font-size:1.8rem; margin:0 0 24px }
        .price { font-size:2rem; font-weight:800 } .plan ul { padding-inline-start:18px; color:var(--muted); line-height:2 }
        .plan.featured { border:2px solid var(--primary) }
        footer { text-align:center; color:var(--muted); padding:32px 0 }
    </style>
</head>
<body>
<div class="wrap">
    <header>
        <div class="logo">🏋️ {{ config('app.name') }}</div>
        <nav class="nav">
            <a class="btn" href="{{ route('locale', $rtl ? 'en' : 'ar') }}">🌐 {{ $rtl ? 'English' : 'العربية' }}</a>
            <a class="btn" href="{{ url('/admin/login') }}">{{ __('تسجيل الدخول') }}</a>
            <a class="btn primary" href="{{ url('/admin/register') }}">{{ __('ابدأ مجاناً') }}</a>
        </nav>
    </header>

    <div class="hero">
        <h1>{{ __('كل إدارة الجيم في مكان واحد') }}</h1>
        <p>{{ __('المشتركين، الاشتراكات، الحضور بالباركود، المدفوعات، المصروفات، المتجر والتقارير — مع تطبيق موبايل لمشتركيك. جرّب مجاناً :days يوم بدون بطاقة.', ['days' => config('saas.trial_days')]) }}</p>
        <a class="btn primary" style="font-size:1.1rem;padding:14px 28px" href="{{ url('/admin/register') }}">{{ __('أنشئ حساب الجيم الآن') }}</a>
    </div>

    <section>
        <div class="grid">
            @foreach ([
                ['👥', 'إدارة المشتركين والاشتراكات', 'باقات بالمدة أو الحصص، تجميد، تجديد، دفع جزئي وتذكير واتساب.'],
                ['📷', 'حضور وانصراف بالباركود', 'كارنيه بباركود و QR، شاشة استقبال سريعة ومسح بكاميرا الموبايل.'],
                ['💰', 'المالية والتقارير', 'إيصالات، مصروفات، أرباح، عمولات المدربين ومديونيات المشتركين.'],
                ['📱', 'تطبيق للمشتركين', 'iOS و Android: الاشتراك، الكارت الرقمي، الحضور، المدفوعات والعروض.'],
                ['🏢', 'أكثر من فرع', 'أدر كل فروعك من حساب واحد مع صلاحيات لكل موظف.'],
                ['🌐', 'عربي و English', 'النظام والتطبيق باللغتين، من اليمين لليسار بالكامل.'],
            ] as [$icon, $title, $text])
                <div class="card"><div style="font-size:1.8rem">{{ $icon }}</div><h3>{{ __($title) }}</h3><p>{{ __($text) }}</p></div>
            @endforeach
        </div>
    </section>

    <section id="pricing">
        <h2>{{ __('الأسعار') }}</h2>
        <div class="grid">
            @foreach ($plans as $i => $plan)
                <div class="card plan {{ $i === 1 ? 'featured' : '' }}">
                    <h3>{{ $plan->localizedName() }}</h3>
                    <div class="price">{{ number_format((float) $plan->price_monthly) }} <small style="font-size:1rem">{{ $plan->currency }} / {{ __('شهرياً') }}</small></div>
                    <p>{{ __('أو :price سنوياً', ['price' => number_format((float) $plan->price_yearly).' '.$plan->currency]) }}</p>
                    <ul>
                        <li>{{ $plan->max_members ? __(':count مشترك', ['count' => number_format($plan->max_members)]) : __('مشتركين بلا حدود') }}</li>
                        <li>{{ $plan->max_staff ? __(':count موظف', ['count' => $plan->max_staff]) : __('موظفين بلا حدود') }}</li>
                        <li>{{ __(':count فرع', ['count' => $plan->max_branches]) }}</li>
                        <li>{{ $plan->mobile_app ? __('تطبيق موبايل للمشتركين') : __('بدون تطبيق موبايل') }}</li>
                    </ul>
                    <a class="btn {{ $i === 1 ? 'primary' : '' }}" style="width:100%;text-align:center" href="{{ url('/admin/register') }}">{{ __('ابدأ التجربة المجانية') }}</a>
                </div>
            @endforeach
        </div>
    </section>

    <footer>© {{ date('Y') }} {{ config('app.name') }} — {{ config('saas.support_email') }}</footer>
</div>
</body>
</html>
