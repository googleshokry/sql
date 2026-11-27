<?php

return [
    // Shown on the gym billing page: how gyms pay the SaaS (manual billing until a gateway is added).
    'payment_instructions' => env('SAAS_PAYMENT_INSTRUCTIONS', 'InstaPay / Vodafone Cash: 01000000000 — اكتب رقم الفاتورة في ملاحظة التحويل'),
    'support_phone' => env('SAAS_SUPPORT_PHONE', '01000000000'),
    'support_email' => env('SAAS_SUPPORT_EMAIL', 'support@example.com'),
    'trial_days' => (int) env('SAAS_TRIAL_DAYS', 14),
];
