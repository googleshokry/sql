<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\TranslatesPageLabels;
use App\Models\Branch;
use App\Models\Plan;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Tenancy;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** The company's plan, remaining days, usage against limits and available plans. */
class Subscription extends Page
{
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'الإعدادات';

    protected static ?int $navigationSort = 0;

    protected static ?string $title = 'الاشتراك والباقة';

    protected string $view = 'filament.pages.subscription';

    public static function canAccess(): bool
    {
        return auth()->user()?->company_id !== null;
    }

    protected function getViewData(): array
    {
        $company = Tenancy::company();

        return [
            'company' => $company,
            'plans' => Plan::query()->where('is_active', true)->orderBy('sort')->get(),
            'usage' => [
                'users' => [User::query()->where('company_id', $company->id)->count(), (int) $company->plan?->max_users],
                'branches' => [Branch::query()->count(), (int) $company->plan?->max_branches],
                'warehouses' => [Warehouse::query()->count(), (int) $company->plan?->max_warehouses],
            ],
            'invoicesThisMonth' => SalesInvoice::query()->whereYear('date', now()->year)->whereMonth('date', now()->month)->count(),
            'supportEmail' => config('saas.support_email'),
            'supportWhatsapp' => config('saas.support_whatsapp'),
        ];
    }
}
