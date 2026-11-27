<?php

return [
    // اتصال قاعدة البيانات المركزية
    'central_connection' => env('DB_CONNECTION', 'mysql'),

    // اسم قاعدة بيانات كل مطعم = البادئة + الـ slug  (مثال: pos_kfc_nasr)
    'database_prefix' => env('TENANT_DB_PREFIX', 'pos_'),

    // مكان ملفات SQLite للمطاعم (للتطوير والاختبارات فقط)
    'sqlite_path' => env('TENANT_SQLITE_PATH', database_path('tenants')),

    'trial_days' => (int) env('TENANT_TRIAL_DAYS', 14),

    // روابط محجوزة لا يمكن استخدامها كاسم مطعم
    'reserved_slugs' => ['admin', 'api', 'app', 'login', 'register', 'billing', 'paymob', 'www', 'livewire', 'filament', 'storage', 'up'],

    // بيانات الدفع اليدوي (تظهر للمطعم في صفحة الاشتراك)
    'manual_payment_instructions' => env('SAAS_MANUAL_PAYMENT', 'InstaPay: pos@instapay — Vodafone Cash: 01000000000'),

    'paymob' => [
        'base_url' => env('PAYMOB_BASE_URL', 'https://accept.paymob.com'),
        'secret_key' => env('PAYMOB_SECRET_KEY'),
        'public_key' => env('PAYMOB_PUBLIC_KEY'),
        'hmac_secret' => env('PAYMOB_HMAC_SECRET'),
        // أرقام الـ integration (كارت، محافظ...) مفصولة بفاصلة
        'integration_ids' => array_values(array_filter(array_map('intval', explode(',', (string) env('PAYMOB_INTEGRATION_IDS', ''))))),
    ],
];
