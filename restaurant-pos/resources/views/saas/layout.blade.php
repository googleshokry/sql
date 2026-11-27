<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('كاشير المطاعم'))</title>
    <style>
        :root { --brand: #f97316; --brand-dark: #c2410c; --ink: #111827; --muted: #6b7280; --line: #e5e7eb; --bg: #fffaf5; --card: #fff; }
        @media (prefers-color-scheme: dark) { :root { --ink: #f3f4f6; --muted: #9ca3af; --line: #374151; --bg: #111214; --card: #1c1d21; } }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Tahoma, "Segoe UI", Arial, sans-serif; color: var(--ink); background: var(--bg); line-height: 1.6; }
        a { color: var(--brand); text-decoration: none; }
        .wrap { max-width: 1120px; margin: 0 auto; padding: 0 16px; }
        header { display: flex; align-items: center; justify-content: space-between; padding: 16px 0; gap: 12px; }
        .logo { font-weight: 800; font-size: 20px; color: var(--ink); display: flex; gap: 8px; align-items: center; }
        .logo span { background: var(--brand); color: #fff; border-radius: 10px; padding: 2px 8px; }
        .nav { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .btn { display: inline-block; padding: 10px 18px; border-radius: 10px; font-weight: 700; border: 1px solid transparent; cursor: pointer; font-size: 15px; }
        .btn-primary { background: var(--brand); color: #fff; }
        .btn-primary:hover { background: var(--brand-dark); }
        .btn-ghost { border-color: var(--line); color: var(--ink); background: transparent; }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: 16px; padding: 24px; }
        input, select { width: 100%; padding: 11px 12px; border: 1px solid var(--line); border-radius: 10px; font-size: 15px; background: var(--card); color: var(--ink); }
        label { display: block; font-size: 14px; font-weight: 600; margin: 14px 0 6px; }
        .hint { font-size: 12px; color: var(--muted); margin-top: 4px; }
        .error { color: #dc2626; font-size: 13px; margin-top: 4px; }
        footer { color: var(--muted); font-size: 13px; text-align: center; padding: 32px 0; }
        .lang { border: 1px solid var(--line); padding: 4px 10px; border-radius: 8px; color: var(--ink); font-size: 13px; }
    </style>
    @stack('styles')
</head>
<body>
<div class="wrap">
    <header>
        <a class="logo" href="{{ route('landing') }}"><span>POS</span> {{ __('كاشير المطاعم') }}</a>
        <nav class="nav">
            <a class="lang" href="{{ route('locale.central', ['locale' => app()->getLocale() === 'ar' ? 'en' : 'ar']) }}">{{ app()->getLocale() === 'ar' ? 'English' : 'العربية' }}</a>
            @yield('nav')
        </nav>
    </header>
    @yield('content')
    <footer>© {{ date('Y') }} {{ __('كاشير المطاعم') }}</footer>
</div>
</body>
</html>
