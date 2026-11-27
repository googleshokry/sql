<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $tenant->name }}</title>
    <style>
        body { font-family: Tahoma, Arial, sans-serif; background: #fff7ed; margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; color: #1f2937; }
        .box { background: #fff; border-radius: 16px; padding: 32px; max-width: 420px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,.08); margin: 16px; }
        h1 { font-size: 20px; margin: 12px 0; }
        p { color: #4b5563; line-height: 1.7; }
        .icon { font-size: 48px; }
        form button { margin-top: 12px; padding: 8px 20px; border-radius: 8px; border: 1px solid #d1d5db; background: #fff; cursor: pointer; }
    </style>
</head>
<body>
<div class="box">
    <div class="icon">⏸️</div>
    <h1>{{ $tenant->name }}</h1>
    <p>{{ $message }}</p>
    @auth
        <form method="POST" action="{{ route('filament.app.auth.logout') }}">
            @csrf
            <button type="submit">{{ __('تسجيل الخروج') }}</button>
        </form>
    @endauth
</div>
</body>
</html>
