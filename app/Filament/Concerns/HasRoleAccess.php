<?php

namespace App\Filament\Concerns;

use App\Filament\Pages\PointOfSale;
use App\Filament\Pages\Reports\AccountLedger;
use App\Filament\Pages\Reports\BalanceSheet;
use App\Filament\Pages\Reports\IncomeStatement;
use App\Filament\Pages\Reports\TrialBalance;
use App\Filament\Pages\Reports\VatReport;
use App\Filament\Resources\AccountResource;
use App\Filament\Resources\AttendanceResource;
use App\Filament\Resources\CrmActivityResource;
use App\Filament\Resources\DepartmentResource;
use App\Filament\Resources\EmployeeResource;
use App\Filament\Resources\InstallmentPlanResource;
use App\Filament\Resources\JournalEntryResource;
use App\Filament\Resources\LeaveRequestResource;
use App\Filament\Resources\PayrollResource;
use App\Filament\Resources\PurchaseInvoiceResource;
use App\Filament\Resources\PurchaseOrderResource;
use App\Filament\Resources\PurchaseReturnResource;
use App\Filament\Resources\SalaryAdvanceResource;
use App\Filament\Resources\StockTransferResource;
use App\Filament\Resources\SupplierResource;
use App\Support\Tenancy;

/**
 * Restricts a screen by the user's role and by the modules included in the company's plan.
 *
 * The class defines: protected static array $roles = ['accountant', 'sales'];
 * The company admin sees everything the plan includes. An empty array = every role.
 */
trait HasRoleAccess
{
    /** Screen => plan feature (module) it belongs to. */
    public const PLAN_FEATURES = [
        PointOfSale::class => 'pos',
        PurchaseInvoiceResource::class => 'purchases',
        PurchaseOrderResource::class => 'purchases',
        PurchaseReturnResource::class => 'purchases',
        SupplierResource::class => 'purchases',
        StockTransferResource::class => 'multi_warehouse',
        InstallmentPlanResource::class => 'installments',
        CrmActivityResource::class => 'crm',
        EmployeeResource::class => 'hr',
        DepartmentResource::class => 'hr',
        AttendanceResource::class => 'hr',
        LeaveRequestResource::class => 'hr',
        SalaryAdvanceResource::class => 'hr',
        PayrollResource::class => 'hr',
        AccountResource::class => 'accounting',
        JournalEntryResource::class => 'accounting',
        AccountLedger::class => 'accounting',
        TrialBalance::class => 'accounting',
        IncomeStatement::class => 'accounting',
        BalanceSheet::class => 'accounting',
        VatReport::class => 'accounting',
    ];

    public static function canAccess(array $parameters = []): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        $feature = self::PLAN_FEATURES[static::class] ?? null;
        if ($feature !== null && ! Tenancy::company()?->hasFeature($feature)) {
            return false;
        }

        return empty(static::$roles) || $user->hasRole(...static::$roles);
    }
}
