<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Services\Accounting;
use Illuminate\Database\Seeder;

/** دليل حسابات افتراضي متوافق مع المنشآت التجارية في السعودية ومصر */
class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        // [code, name, type, parent_code, is_group, system_key]
        $accounts = [
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

        $ids = [];
        foreach ($accounts as [$code, $name, $type, $parent, $group, $key]) {
            $account = Account::query()->updateOrCreate(['code' => $code], [
                'name' => $name,
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
