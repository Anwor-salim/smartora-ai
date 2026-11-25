<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class CompanyPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('company')
            ->path('company')
            ->login()
            ->brandName(function () {
                $user = auth()->user();
                $companyName = $user?->company?->name;
                if ($companyName) {
                    return $companyName . ' | سمارتورا AI';
                }
                return app()->getLocale() === 'ar' ? 'سمارتورا AI - بوابة الشركات' : 'SmartOra AI - Company Portal';
            })
            ->brandLogo(fn () => view('filament.components.brand-logo', ['panel' => 'company']))
            ->brandLogoHeight('2.25rem')
            ->colors([
                'primary' => Color::Sky,
                'gray' => Color::Slate,
            ])
            ->font('Tajawal')
            ->sidebarWidth('16rem')
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth(\Filament\Support\Enums\MaxWidth::Full)
            ->renderHook(
                \Filament\View\PanelsRenderHook::HEAD_END,
                fn () => view('filament.components.custom-theme'),
            )
            ->renderHook(
                \Filament\View\PanelsRenderHook::PAGE_START,
                fn () => view('filament.components.super-admin-impersonation-banner'),
            )
            ->userMenuItems([
                \Filament\Navigation\MenuItem::make()
                    ->label(fn () => 'العودة للوحة الإدارة العامة (Admin Panel)')
                    ->icon('heroicon-o-shield-check')
                    ->url('/admin/companies')
                    ->visible(fn () => auth()->user()?->isSuperAdmin() ?? false),
            ])
            ->discoverResources(in: app_path('Filament/Company/Resources'), for: 'App\\Filament\\Company\\Resources')
            ->discoverPages(in: app_path('Filament/Company/Pages'), for: 'App\\Filament\\Company\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Company/Widgets'), for: 'App\\Filament\\Company\\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                \App\Http\Middleware\SetLocaleMiddleware::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                \App\Http\Middleware\FilamentAuthenticate::class,
            ]);
    }
}
