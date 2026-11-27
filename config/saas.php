<?php

return [
    /*
    | Contact details shown to companies on the subscription page
    | (payments are activated manually from the /platform panel).
    */
    'support_email' => env('SAAS_SUPPORT_EMAIL', 'support@example.com'),
    'support_whatsapp' => env('SAAS_SUPPORT_WHATSAPP'),

    /* Allow new companies to sign up from /admin/register */
    'registration_enabled' => (bool) env('SAAS_REGISTRATION', true),
];
