<?php

namespace App\Providers\Filament;

use App\Filament\Auth\Register;
use App\Filament\Pages\Dashboard;
use App\Http\Middleware\EnsureActiveSubscription;
use App\Http\Middleware\SetLocale;
use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->registration(config('saas.registration_enabled') ? Register::class : null)
            ->brandName(fn () => app()->getLocale() === 'en' && Setting::get('company_name_en') ? Setting::get('company_name_en') : Setting::get('company_name', config('app.name')))
            ->colors([
                'primary' => Color::Indigo,
                'gray' => Color::Slate,
            ])
            ->font('Cairo')
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth('full')
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->navigationGroups([
                NavigationGroup::make(fn (): string => __('المبيعات'))->icon(Heroicon::OutlinedShoppingBag),
                NavigationGroup::make(fn (): string => __('المشتريات'))->icon(Heroicon::OutlinedTruck),
                NavigationGroup::make(fn (): string => __('المخزون'))->icon(Heroicon::OutlinedArchiveBox),
                NavigationGroup::make(fn (): string => __('المالية'))->icon(Heroicon::OutlinedWallet),
                NavigationGroup::make(fn (): string => __('المحاسبة'))->icon(Heroicon::OutlinedCalculator),
                NavigationGroup::make(fn (): string => __('التقارير'))->icon(Heroicon::OutlinedChartBar),
                NavigationGroup::make(fn (): string => __('الموارد البشرية'))->icon(Heroicon::OutlinedUserGroup),
                NavigationGroup::make(fn (): string => __('الإعدادات'))->icon(Heroicon::OutlinedCog6Tooth)->collapsed(),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([Dashboard::class])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => Blade::render('<link rel="stylesheet" href="{{ asset(\'css/erp.css\') }}">'))
            ->userMenuItems([
                Action::make('switchLanguage')
                    ->label(fn (): string => app()->getLocale() === 'ar' ? 'English' : 'العربية')
                    ->icon(Heroicon::OutlinedLanguage)
                    ->url(fn (): string => route('locale.switch', app()->getLocale() === 'ar' ? 'en' : 'ar')),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                SetLocale::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsureActiveSubscription::class,
            ])
            ->renderHook(PanelsRenderHook::BODY_START, fn () => view('filament.partials.subscription-banner'));
    }
}
