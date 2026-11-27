<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>@yield('title')</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Tahoma, Arial, sans-serif; margin: 0; background: #eee; color: #000; }
        .paper { width: 80mm; margin: 10px auto; background: #fff; padding: 4mm; font-size: 12px; }
        h1, h2, h3 { margin: 0; text-align: center; }
        h1 { font-size: 18px; } h2 { font-size: 15px; }
        .center { text-align: center; }
        .muted { color: #333; font-size: 11px; }
        hr { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 2px 0; vertical-align: top; text-align: right; }
        th { border-bottom: 1px solid #000; }
        .num { text-align: left; white-space: nowrap; }
        .total { font-size: 16px; font-weight: bold; }
        .big { font-size: 22px; font-weight: bold; text-align: center; }
        .actions { text-align: center; margin: 10px; }
        .actions button { padding: 8px 20px; font-size: 14px; cursor: pointer; }
        @media print {
            body { background: #fff; }
            .paper { margin: 0; width: 100%; }
            .actions { display: none; }
            @page { size: 80mm auto; margin: 0; }
        }
    </style>
</head>
<body>
<div class="actions">
    <button onclick="window.print()">🖨️ طباعة</button>
    <button onclick="window.close()">إغلاق</button>
</div>
<div class="paper">
    @yield('content')
</div>
<script>
    window.addEventListener('load', () => setTimeout(() => window.print(), 300));
</script>
</body>
</html>
