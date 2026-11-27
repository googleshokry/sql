<?php

namespace App\Providers\Filament;

use App\Http\Middleware\SetLocale;
use App\Support\LocalAvatarProvider;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/** SaaS owner panel at /super: gyms (tenants), SaaS plans, invoices. Only users with is_super_admin. */
class SuperPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('super')
            ->path('super')
            ->login()
            ->brandName(fn () => __('إدارة المنصة'))
            ->defaultAvatarProvider(LocalAvatarProvider::class)
            ->font('Cairo')
            ->colors(['primary' => Color::Indigo, 'danger' => Color::Rose, 'success' => Color::Emerald])
            ->userMenuItems([
                Action::make('language')
                    ->label(fn () => app()->getLocale() === 'ar' ? 'English' : 'العربية')
                    ->icon('heroicon-o-language')
                    ->url(fn () => route('locale', app()->getLocale() === 'ar' ? 'en' : 'ar')),
            ])
            ->renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, fn () => view('filament.language-links'))
            ->discoverResources(in: app_path('Filament/Super/Resources'), for: 'App\Filament\Super\Resources')
            ->discoverWidgets(in: app_path('Filament/Super/Widgets'), for: 'App\Filament\Super\Widgets')
            ->pages([Dashboard::class])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->middleware([SetLocale::class], isPersistent: true)
            ->authMiddleware([Authenticate::class]);
    }
}
