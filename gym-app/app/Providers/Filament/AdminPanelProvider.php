<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use App\Models\Setting;
use App\Support\LocalAvatarProvider;
use Illuminate\Support\Facades\Storage;
use App\Enums\NavGroup;
use App\Filament\Pages\Billing;
use App\Filament\Pages\Tenancy\RegisterGym;
use App\Http\Middleware\ApplyTenancy;
use App\Http\Middleware\EnsureGymHasAccess;
use App\Models\Gym;
use Filament\Navigation\NavigationGroup;
use App\Http\Middleware\SetLocale;
use Filament\Actions\Action;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
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
            ->registration()
            ->passwordReset()
            ->profile()
            // Multi-tenancy: every gym is a tenant at /admin/{gym-slug}.
            ->tenant(Gym::class, slugAttribute: 'slug')
            ->tenantRegistration(RegisterGym::class)
            ->tenantMenuItems([
                Action::make('billing')->label(fn () => __('الاشتراك والفواتير'))->icon('heroicon-o-credit-card')
                    ->url(fn () => Billing::getUrl()),
            ])
            ->tenantMiddleware([ApplyTenancy::class, EnsureGymHasAccess::class], isPersistent: true)
            ->brandName(fn () => Setting::get('gym_name'))
            ->brandLogo(fn () => Setting::get('gym_logo') ? Storage::disk('public')->url(Setting::get('gym_logo')) : null)
            ->brandLogoHeight('2.5rem')
            ->defaultAvatarProvider(LocalAvatarProvider::class)
            ->font('Cairo')
            ->colors([
                'primary' => Color::Amber,
                'danger' => Color::Rose,
                'success' => Color::Emerald,
            ])
            ->sidebarCollapsibleOnDesktop()
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->navigationGroups(collect(NavGroup::cases())->mapWithKeys(fn (NavGroup $group) => [
                // Labels are resolved lazily so they follow the current request's language.
                $group->name => NavigationGroup::make()->label(fn () => $group->getLabel()),
            ])->all())
            ->userMenuItems([
                Action::make('language')
                    ->label(fn () => app()->getLocale() === 'ar' ? 'English' : 'العربية')
                    ->icon('heroicon-o-language')
                    ->url(fn () => route('locale', app()->getLocale() === 'ar' ? 'en' : 'ar')),
            ])
            ->renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, fn () => view('filament.language-links'))
            ->renderHook(PanelsRenderHook::AUTH_REGISTER_FORM_AFTER, fn () => view('filament.language-links'))
            ->renderHook(PanelsRenderHook::CONTENT_START, fn () => view('filament.trial-banner'))
            ->renderHook(PanelsRenderHook::GLOBAL_SEARCH_AFTER, fn () => view('filament.language-links'))
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
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
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
