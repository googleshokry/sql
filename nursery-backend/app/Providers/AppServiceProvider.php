<?php

namespace App\Providers;

use App\Support\CurrentNursery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // scoped = reset per request / per queued job (Octane- and Horizon-safe)
        $this->app->scoped(CurrentNursery::class);

        // Swap for a real SMS / WhatsApp provider in production.
        $this->app->bind(\App\Services\Otp\SmsSender::class, \App\Services\Otp\LogSmsSender::class);
    }

    public function boot(): void
    {
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
    }
}
