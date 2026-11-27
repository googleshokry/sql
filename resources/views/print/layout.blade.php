<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>@yield('title')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Cairo', Tahoma, sans-serif; color: #111827; margin: 0; background: #f3f4f6; font-size: 13px; }
        .page { background: #fff; width: 210mm; min-height: 297mm; margin: 10px auto; padding: 14mm; box-shadow: 0 0 8px rgba(0,0,0,.1); }
        .head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #4f46e5; padding-bottom: 10px; margin-bottom: 14px; }
        .head h1 { margin: 0; font-size: 20px; }
        .muted { color: #6b7280; }
        .title { text-align: center; margin: 8px 0 14px; }
        .title h2 { margin: 0; font-size: 20px; color: #4f46e5; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 14px; }
        .box { border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px; }
        .box b { display: inline-block; min-width: 110px; color: #374151; }
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.items th { background: #4f46e5; color: #fff; padding: 7px; font-weight: 600; }
        table.items td { border-bottom: 1px solid #e5e7eb; padding: 7px; text-align: center; }
        table.items td.desc { text-align: right; }
        .totals { width: 320px; margin-inline-start: auto; border-collapse: collapse; }
        .totals td { padding: 6px 10px; border-bottom: 1px solid #e5e7eb; }
        .totals tr.grand td { font-weight: 800; font-size: 15px; background: #eef2ff; }
        .foot { margin-top: 24px; display: flex; justify-content: space-between; align-items: flex-end; }
        .sign { width: 200px; text-align: center; border-top: 1px solid #9ca3af; padding-top: 6px; }
        .toolbar { text-align: center; margin: 10px; }
        .toolbar button, .toolbar a { font-family: inherit; background: #4f46e5; color: #fff; border: 0; padding: 8px 18px; border-radius: 6px; cursor: pointer; text-decoration: none; margin: 0 4px; font-size: 13px; }
        @media print { body { background: #fff; } .page { margin: 0; box-shadow: none; width: auto; min-height: auto; } .toolbar { display: none; } @page { size: A4; margin: 8mm; } }
        @yield('styles')
    </style>
</head>
<body>
<div class="toolbar">
    <button onclick="window.print()">🖨️ طباعة</button>
    @yield('toolbar')
</div>
@yield('content')
@if(request('auto'))<script>window.onload = () => window.print();</script>@endif
</body>
</html>
