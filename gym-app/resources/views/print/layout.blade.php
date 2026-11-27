<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>@yield('title')</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Cairo', Tahoma, sans-serif; margin: 0; padding: 20px; color: #111; background: #f3f4f6; }
        .sheet { background: #fff; margin: 0 auto; padding: 16px; }
        .center { text-align: center; } .muted { color: #6b7280; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        td, th { padding: 4px 2px; text-align: start; border-bottom: 1px dashed #ddd; }
        .btn { display: inline-block; margin: 12px auto; padding: 8px 20px; background: #f59e0b; color: #fff; border: 0; border-radius: 6px; font-family: inherit; cursor: pointer; }
        @media print { body { background: #fff; padding: 0; } .no-print { display: none; } }
    </style>
    @stack('styles')
</head>
<body onload="setTimeout(() => window.print(), 300)">
    @yield('content')
    <div class="center no-print"><button class="btn" onclick="window.print()">طباعة</button></div>
</body>
</html>
