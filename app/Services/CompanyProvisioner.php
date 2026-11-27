<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Department;
use App\Models\ExpenseCategory;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\Treasury;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;

/**
 * Creates a ready-to-use company: chart of accounts, settings, branch, warehouse,
 * treasuries, units, categories and the owner (admin) user.
 */
class CompanyProvisioner
{
    /**
     * Default chart of accounts: [code, name, type, parent code, is group, system key].
     *
     * @var list<array{0: string, 1: string, 2: string, 3: ?string, 4: bool, 5: ?string}>
     */
    public const CHART_OF_ACCOUNTS = [
        ['1', 'الأصول', 'asset', null, true, 'assets'],
        ['11', 'الأصول المتداولة', 'asset', '1', true, null],
        ['111', 'النقدية بالخزائن', 'asset', '11', true, 'cash'],
        ['112', 'البنوك', 'asset', '11', true, 'banks'],
        ['113', 'العملاء (ذمم مدينة)', 'asset', '11', false, 'receivable'],
        ['114', 'المخزون', 'asset', '11', false, 'inventory'],
        ['115', 'ضريبة القيمة المضافة - مدخلات', 'asset', '11', false, 'vat_input'],
        ['116', 'سلف وعهد الموظفين', 'asset', '11', false, 'employee_advances'],
        ['12', 'الأصول الثابتة', 'asset', '1', true, null],
        ['121', 'الأثاث والتجهيزات', 'asset', '12', false, null],
        ['122', 'السيارات', 'asset', '12', false, null],
        ['123', 'الأجهزة والمعدات', 'asset', '12', false, null],

        ['2', 'الخصوم', 'liability', null, true, 'liabilities'],
        ['21', 'الخصوم المتداولة', 'liability', '2', true, null],
        ['211', 'الموردين (ذمم دائنة)', 'liability', '21', false, 'payable'],
        ['212', 'ضريبة القيمة المضافة - مخرجات', 'liability', '21', false, 'vat_output'],
        ['213', 'رواتب مستحقة', 'liability', '21', false, 'salaries_payable'],
        ['214', 'قروض قصيرة الأجل', 'liability', '21', false, null],

        ['3', 'حقوق الملكية', 'equity', null, true, 'equity'],
        ['31', 'رأس المال', 'equity', '3', false, 'capital'],
        ['32', 'الأرباح المحتجزة', 'equity', '3', false, 'retained_earnings'],
        ['33', 'أرصدة افتتاحية', 'equity', '3', false, 'opening_equity'],
        ['34', 'جاري الشركاء', 'equity', '3', false, null],

        ['4', 'الإيرادات', 'revenue', null, true, 'revenues'],
        ['41', 'إيرادات المبيعات', 'revenue', '4', false, 'sales'],
        ['42', 'مردودات المبيعات', 'revenue', '4', false, 'sales_returns'],
        ['43', 'إيرادات الشحن', 'revenue', '4', false, 'shipping_revenue'],
        ['44', 'إيرادات أخرى', 'revenue', '4', false, 'other_revenue'],
        ['45', 'فروقات الجرد', 'revenue', '4', false, 'inventory_adjustments'],

        ['5', 'المصروفات', 'expense', null, true, 'expenses'],
        ['51', 'تكلفة البضاعة المباعة', 'expense', '5', false, 'cogs'],
        ['52', 'الرواتب والأجور', 'expense', '5', false, 'salaries'],
        ['53', 'الإيجارات', 'expense', '5', false, 'rent'],
        ['54', 'الكهرباء والمياه', 'expense', '5', false, 'utilities'],
        ['55', 'مصروفات عمومية وإدارية', 'expense', '5', false, 'general_expenses'],
        ['56', 'مصروفات تسويق ودعاية', 'expense', '5', false, 'marketing'],
        ['57', 'مصروفات نقل ومواصلات', 'expense', '5', false, 'transport'],
        ['58', 'الإهلاك', 'expense', '5', false, 'depreciation'],
    ];

    /**
     * Register a new company on a plan's trial and create its owner.
     *
     * @param  array{company_name: string, name: string, email: string, password: string, phone?: ?string, locale?: ?string}  $data
     */
    public static function register(array $data, ?Plan $plan = null): User
    {
        $plan ??= Plan::query()->where('is_active', true)->orderBy('sort')->first();

        return DB::transaction(function () use ($data, $plan) {
            $company = Company::create([
                'name' => $data['company_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'plan_id' => $plan?->id,
                'status' => 'trial',
                'trial_ends_at' => now()->addDays($plan?->trial_days ?? 14),
            ]);

            return static::provision($company, $data);
        });
    }

    /**
     * @param  array{name: string, email: string, password: string, locale?: ?string, company_name?: string}  $owner
     */
    public static function provision(Company $company, array $owner): User
    {
        // default names (branch, units, accounts...) are created in the owner's language
        $previousLocale = app()->getLocale();
        app()->setLocale($owner['locale'] ?? $previousLocale);

        try {
            return static::createDefaults($company, $owner);
        } finally {
            app()->setLocale($previousLocale);
        }
    }

    /**
     * @param  array{name: string, email: string, password: string, locale?: ?string}  $owner
     */
    protected static function createDefaults(Company $company, array $owner): User
    {
        return Tenancy::run($company, function () use ($company, $owner) {
            static::installChartOfAccounts();

            Setting::setMany(array_merge(Setting::DEFAULTS, [
                'company_name' => $company->name,
                'company_name_en' => $company->name,
                'phone' => $company->phone ?? '',
                'email' => $company->email ?? '',
            ]));

            $branch = Branch::create(['name' => __('الفرع الرئيسي')]);
            Warehouse::create(['name' => __('المخزن الرئيسي'), 'branch_id' => $branch->id, 'is_default' => true]);
            Treasury::create(['name' => __('الخزينة الرئيسية'), 'type' => 'cash', 'branch_id' => $branch->id, 'is_default' => true]);
            Treasury::create(['name' => __('البنك'), 'type' => 'bank', 'branch_id' => $branch->id]);

            foreach (['قطعة' => 'قطعة', 'كرتونة' => 'كرتونة', 'كيلوجرام' => 'كجم', 'لتر' => 'لتر', 'متر' => 'م', 'ساعة' => 'ساعة'] as $name => $short) {
                Unit::create(['name' => __($name), 'short_name' => __($short)]);
            }
            foreach (['عام', 'خدمات'] as $name) {
                Category::create(['name' => __($name)]);
            }
            $expenseCategories = [
                'إيجار' => 'rent', 'كهرباء ومياه' => 'utilities', 'دعاية وإعلان' => 'marketing',
                'نقل ومواصلات' => 'transport', 'مصروفات نثرية' => 'general_expenses',
            ];
            foreach ($expenseCategories as $name => $key) {
                ExpenseCategory::create(['name' => __($name), 'account_id' => Accounting::account($key)->id]);
            }
            foreach (['الإدارة', 'المبيعات', 'المخازن', 'المحاسبة'] as $name) {
                Department::create(['name' => __($name)]);
            }
            Customer::create(['name' => __('عميل نقدي')]);
            Supplier::create(['name' => __('مورد عام')]);

            return User::create([
                'company_id' => $company->id,
                'name' => $owner['name'],
                'email' => $owner['email'],
                'password' => $owner['password'],
                'role' => 'admin',
                'is_active' => true,
                'branch_id' => $branch->id,
                'locale' => $owner['locale'] ?? app()->getLocale(),
            ]);
        });
    }

    /** Creates (or updates) the default chart of accounts for the current company. */
    public static function installChartOfAccounts(): void
    {
        $ids = [];
        foreach (self::CHART_OF_ACCOUNTS as [$code, $name, $type, $parent, $group, $key]) {
            $account = Account::query()->updateOrCreate(['code' => $code], [
                'name' => __($name),
                'type' => $type,
                'parent_id' => $parent ? $ids[$parent] : null,
                'is_group' => $group,
                'is_system' => $key !== null,
                'system_key' => $key,
            ]);
            $ids[$code] = $account->id;
        }

        Accounting::flushCache();
    }
}
