<?php

namespace App\Filament\Pages;

use App\Http\Controllers\Api\DriverTrackingController;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** خريطة الطيارين المباشرة */
class DriversMap extends Page
{
    protected string $view = 'filament.pages.drivers-map';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|UnitEnum|null $navigationGroup = \App\Filament\NavGroup::Delivery;

    protected static ?int $navigationSort = 0;

    protected Width|string|null $maxContentWidth = Width::Full;

    public array $drivers = [];

    public static function getNavigationLabel(): string
    {
        return __('خريطة الطيارين');
    }

    public function getTitle(): string
    {
        return __('خريطة الطيارين');
    }

    public function mount(): void
    {
        $this->refreshDrivers();
    }

    public function refreshDrivers(): void
    {
        $this->drivers = DriverTrackingController::payload();
    }

    public static function getNavigationBadge(): ?string
    {
        $live = \App\Models\Driver::where('is_online', true)
            ->where('location_updated_at', '>=', now()->subMinutes(\App\Models\Driver::STALE_MINUTES))->count();

        return $live ? (string) $live : null;
    }
}
