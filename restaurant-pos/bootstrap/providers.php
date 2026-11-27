<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\PlatformPanelProvider;
use App\Providers\Filament\RestaurantPanelProvider;

return [
    AppServiceProvider::class,
    RestaurantPanelProvider::class,
    PlatformPanelProvider::class,
];
