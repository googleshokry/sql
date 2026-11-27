<?php

return [
    'restaurant_name' => env('POS_RESTAURANT_NAME', 'مطعمي'),
    'restaurant_phone' => env('POS_RESTAURANT_PHONE', ''),
    'restaurant_address' => env('POS_RESTAURANT_ADDRESS', ''),
    'currency' => env('POS_CURRENCY', 'ج.م'),
    // نسبة الضريبة (مثلاً 14 = 14%)
    'tax_rate' => (float) env('POS_TAX_RATE', 0),
    // نسبة الخدمة على طلبات الصالة فقط
    'service_rate' => (float) env('POS_SERVICE_RATE', 0),
    // مركز الخريطة الافتراضي (القاهرة)
    'map_lat' => (float) env('POS_MAP_LAT', 30.0444),
    'map_lng' => (float) env('POS_MAP_LNG', 31.2357),
    'receipt_footer' => env('POS_RECEIPT_FOOTER', 'شكراً لزيارتكم'),
];
