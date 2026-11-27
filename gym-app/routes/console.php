<?php

use App\Services\AttendanceService;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('gym:refresh-subscriptions', function () {
    $this->info('Updated '.SubscriptionService::refreshAll().' subscriptions');
})->purpose('Update subscription statuses (active / expired / frozen)');

Artisan::command('gym:auto-checkout', function () {
    $total = 0;
    // Each gym has its own "auto checkout after N hours" setting.
    App\Models\Gym::query()->each(function (App\Models\Gym $gym) use (&$total) {
        $total += App\Support\Tenancy::run($gym, fn () => AttendanceService::autoCheckout());
    });
    $this->info("Auto checked-out {$total} attendances");
})->purpose('Close attendances left open for too long');

Artisan::command('saas:billing', function () {
    // Trials that ended and paid periods that lapsed lose access; issue renewal invoices a week ahead.
    $expired = App\Models\Gym::where('status', 'trial')->where('trial_ends_at', '<', now())->update(['status' => 'past_due']);
    App\Models\Gym::where('status', 'active')->where('subscription_ends_at', '<', now())->update(['status' => 'past_due']);
    App\Models\Gym::where('status', 'active')->whereBetween('subscription_ends_at', [now(), now()->addDays(7)])
        ->whereDoesntHave('invoices', fn ($q) => $q->where('status', 'unpaid'))
        ->each(fn (App\Models\Gym $gym) => App\Models\GymInvoice::issueFor($gym));
    $this->info("Trials ended: {$expired}");
})->purpose('SaaS subscription housekeeping');

Schedule::command('gym:refresh-subscriptions')->dailyAt('00:05');
Schedule::command('gym:auto-checkout')->hourly();
Schedule::command('saas:billing')->dailyAt('01:00');
