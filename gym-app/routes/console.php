<?php

use App\Services\AttendanceService;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('gym:refresh-subscriptions', function () {
    $this->info('تم تحديث حالة '.SubscriptionService::refreshAll().' اشتراك');
})->purpose('Update subscription statuses (active / expired / frozen)');

Artisan::command('gym:auto-checkout', function () {
    $this->info('تم تسجيل خروج تلقائي لـ '.AttendanceService::autoCheckout().' مشترك');
})->purpose('Close attendances left open for too long');

Schedule::command('gym:refresh-subscriptions')->dailyAt('00:05');
Schedule::command('gym:auto-checkout')->hourly();
