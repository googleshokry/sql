<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Turns the single-company ERP into a multi-tenant SaaS:
 * plans + companies tables, a company_id on every business table, and per-company unique keys.
 * Existing data (single-company installs) is moved into a default company.
 */
return new class extends Migration
{
    /** @var list<string> */
    public const TENANT_TABLES = [
        'settings', 'branches', 'warehouses', 'accounts', 'treasuries', 'journal_entries', 'journal_entry_lines',
        'customers', 'suppliers', 'categories', 'units', 'products', 'product_stocks', 'stock_movements',
        'quotations', 'quotation_items', 'sales_invoices', 'sales_invoice_items', 'sales_returns', 'sales_return_items',
        'purchase_orders', 'purchase_order_items', 'purchase_invoices', 'purchase_invoice_items', 'purchase_returns', 'purchase_return_items',
        'payments', 'treasury_transfers', 'expense_categories', 'expenses', 'stock_transfers', 'stock_transfer_items',
        'stock_adjustments', 'stock_adjustment_items', 'installment_plans', 'installments',
        'departments', 'employees', 'attendances', 'leave_requests', 'salary_advances', 'payrolls', 'payroll_items', 'crm_activities',
    ];

    /** @var array<string, list<string>> columns that were globally unique and become unique per company */
    public const PER_COMPANY_UNIQUE = [
        'settings' => ['key'],
        'accounts' => ['code', 'system_key'],
        'journal_entries' => ['number'],
        'customers' => ['code'],
        'suppliers' => ['code'],
        'products' => ['sku', 'barcode'],
        'quotations' => ['number'],
        'sales_invoices' => ['number'],
        'sales_returns' => ['number'],
        'purchase_orders' => ['number'],
        'purchase_invoices' => ['number'],
        'purchase_returns' => ['number'],
        'payments' => ['number'],
        'treasury_transfers' => ['number'],
        'expenses' => ['number'],
        'stock_transfers' => ['number'],
        'stock_adjustments' => ['number'],
        'installment_plans' => ['number'],
        'employees' => ['code'],
        'payrolls' => ['number'],
    ];

    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->string('code')->unique();
            $table->decimal('monthly_price', 10, 2)->default(0);
            $table->decimal('yearly_price', 10, 2)->default(0);
            $table->unsignedInteger('max_users')->default(1);
            $table->unsignedInteger('max_branches')->default(1);
            $table->unsignedInteger('max_warehouses')->default(1);
            $table->json('features')->nullable();
            $table->unsignedSmallInteger('trial_days')->default(14);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('country', 2)->nullable();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('trial'); // trial, active, suspended, cancelled
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('subscription_ends_at')->nullable();
            $table->string('billing_cycle')->default('monthly');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->boolean('is_super_admin')->default(false)->after('role');
        });

        foreach (self::TENANT_TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            });
        }

        $this->moveExistingDataToDefaultCompany();

        foreach (self::PER_COMPANY_UNIQUE as $name => $columns) {
            Schema::table($name, function (Blueprint $table) use ($name, $columns) {
                foreach ($columns as $column) {
                    $table->dropUnique("{$name}_{$column}_unique");
                    $table->unique(['company_id', $column]);
                }
            });
        }
    }

    protected function moveExistingDataToDefaultCompany(): void
    {
        if (! DB::table('users')->exists() && ! DB::table('settings')->exists()) {
            return;
        }

        $name = DB::table('settings')->where('key', 'company_name')->value('value') ?: 'Default company';
        $companyId = DB::table('companies')->insertGetId([
            'name' => $name,
            'slug' => 'default',
            'status' => 'active',
            'subscription_ends_at' => now()->addYears(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (self::TENANT_TABLES as $table) {
            DB::table($table)->update(['company_id' => $companyId]);
        }
        DB::table('users')->update(['company_id' => $companyId]);
    }

    /** Only safe while each code/number is still unique across all companies. */
    public function down(): void
    {
        foreach ([...self::TENANT_TABLES, 'users'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropForeign(['company_id']));
        }

        foreach (self::PER_COMPANY_UNIQUE as $name => $columns) {
            Schema::table($name, function (Blueprint $table) use ($columns) {
                foreach ($columns as $column) {
                    $table->dropUnique(['company_id', $column]);
                    $table->unique($column);
                }
            });
        }

        foreach ([...self::TENANT_TABLES, 'users'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('company_id'));
        }

        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_super_admin'));
        Schema::dropIfExists('companies');
        Schema::dropIfExists('plans');
    }
};
