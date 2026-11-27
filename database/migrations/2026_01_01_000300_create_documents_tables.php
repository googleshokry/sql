<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * المستندات: عروض أسعار، فواتير مبيعات، مرتجعات مبيعات، أوامر شراء، فواتير مشتريات، مرتجعات مشتريات
     * party: customer_id أو supplier_id
     */
    protected array $documents = [
        'quotations' => 'customer',
        'sales_invoices' => 'customer',
        'sales_returns' => 'customer',
        'purchase_orders' => 'supplier',
        'purchase_invoices' => 'supplier',
        'purchase_returns' => 'supplier',
    ];

    public function up(): void
    {
        foreach ($this->documents as $table => $party) {
            Schema::create($table, function (Blueprint $t) use ($table, $party) {
                $t->id();
                $t->string('number')->unique();
                $t->date('date');
                $t->foreignId("{$party}_id")->nullable()->constrained($party.'s')->nullOnDelete();
                $t->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
                $t->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
                $t->string('status')->default('draft');

                if ($table === 'quotations' || $table === 'purchase_orders') {
                    $t->date('valid_until')->nullable();
                }
                if ($table === 'sales_invoices' || $table === 'purchase_invoices') {
                    $t->date('due_date')->nullable();
                    $t->string('payment_status')->default('unpaid'); // unpaid, partial, paid
                    $t->decimal('paid_amount', 15, 2)->default(0);
                    $t->string('reference')->nullable();
                }
                if ($table === 'sales_invoices') {
                    $t->boolean('is_pos')->default(false);
                    $t->foreignId('quotation_id')->nullable()->constrained()->nullOnDelete();
                    $t->string('payment_method')->nullable();
                }
                if ($table === 'purchase_invoices') {
                    $t->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
                }
                if ($table === 'sales_returns') {
                    $t->foreignId('sales_invoice_id')->nullable()->constrained()->nullOnDelete();
                    $t->foreignId('treasury_id')->nullable()->constrained()->nullOnDelete();
                }
                if ($table === 'purchase_returns') {
                    $t->foreignId('purchase_invoice_id')->nullable()->constrained()->nullOnDelete();
                    $t->foreignId('treasury_id')->nullable()->constrained()->nullOnDelete();
                }

                $t->decimal('subtotal', 15, 2)->default(0);
                $t->string('discount_type')->default('fixed'); // fixed, percent
                $t->decimal('discount_value', 15, 2)->default(0);
                $t->decimal('discount_amount', 15, 2)->default(0);
                $t->decimal('tax_amount', 15, 2)->default(0);
                $t->decimal('shipping', 15, 2)->default(0);
                $t->decimal('total', 15, 2)->default(0);
                $t->text('notes')->nullable();
                $t->text('terms')->nullable();
                $t->timestamp('posted_at')->nullable();
                $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamps();
            });

            $singular = str($table)->singular()->toString();
            Schema::create("{$singular}_items", function (Blueprint $t) use ($table, $singular) {
                $t->id();
                $t->foreignId("{$singular}_id")->constrained($table)->cascadeOnDelete();
                $t->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
                $t->string('description')->nullable();
                $t->decimal('quantity', 15, 3)->default(1);
                $t->decimal('unit_price', 15, 2)->default(0);
                $t->decimal('discount_percent', 5, 2)->default(0);
                $t->decimal('tax_rate', 5, 2)->default(0);
                $t->decimal('tax_amount', 15, 2)->default(0);
                $t->decimal('total', 15, 2)->default(0); // بعد الخصم وقبل الضريبة
                $t->decimal('unit_cost', 15, 4)->default(0);
                $t->timestamps();
            });
        }

        // سندات القبض والصرف
        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->string('number')->unique();
            $t->string('direction'); // in (قبض), out (صرف)
            $t->date('date');
            $t->foreignId('treasury_id')->constrained()->restrictOnDelete();
            $t->nullableMorphs('party');
            $t->nullableMorphs('payable'); // فاتورة مبيعات / مشتريات
            $t->foreignId('account_id')->nullable()->constrained()->nullOnDelete(); // حساب مقابل لو بدون طرف
            $t->decimal('amount', 15, 2);
            $t->string('method')->default('cash'); // cash, bank_transfer, card, cheque
            $t->string('reference')->nullable();
            $t->string('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        // التحويل بين الخزائن
        Schema::create('treasury_transfers', function (Blueprint $t) {
            $t->id();
            $t->string('number')->unique();
            $t->date('date');
            $t->foreignId('from_treasury_id')->constrained('treasuries')->restrictOnDelete();
            $t->foreignId('to_treasury_id')->constrained('treasuries')->restrictOnDelete();
            $t->decimal('amount', 15, 2);
            $t->string('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('expense_categories', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('expenses', function (Blueprint $t) {
            $t->id();
            $t->string('number')->unique();
            $t->date('date');
            $t->foreignId('expense_category_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('treasury_id')->constrained()->restrictOnDelete();
            $t->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $t->decimal('amount', 15, 2);
            $t->decimal('tax_rate', 5, 2)->default(0);
            $t->decimal('tax_amount', 15, 2)->default(0);
            $t->decimal('total', 15, 2)->default(0);
            $t->string('description')->nullable();
            $t->string('attachment')->nullable();
            $t->timestamps();
        });

        // التحويلات المخزنية
        Schema::create('stock_transfers', function (Blueprint $t) {
            $t->id();
            $t->string('number')->unique();
            $t->date('date');
            $t->foreignId('from_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $t->foreignId('to_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $t->string('status')->default('draft');
            $t->string('notes')->nullable();
            $t->timestamp('posted_at')->nullable();
            $t->timestamps();
        });
        Schema::create('stock_transfer_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->decimal('quantity', 15, 3);
            $t->timestamps();
        });

        // الجرد / التسويات المخزنية
        Schema::create('stock_adjustments', function (Blueprint $t) {
            $t->id();
            $t->string('number')->unique();
            $t->date('date');
            $t->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $t->string('status')->default('draft');
            $t->string('notes')->nullable();
            $t->timestamp('posted_at')->nullable();
            $t->timestamps();
        });
        Schema::create('stock_adjustment_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('stock_adjustment_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->decimal('system_quantity', 15, 3)->default(0);
            $t->decimal('actual_quantity', 15, 3)->default(0);
            $t->decimal('difference', 15, 3)->default(0);
            $t->timestamps();
        });

        // التقسيط
        Schema::create('installment_plans', function (Blueprint $t) {
            $t->id();
            $t->string('number')->unique();
            $t->foreignId('customer_id')->constrained()->restrictOnDelete();
            $t->foreignId('sales_invoice_id')->nullable()->constrained()->nullOnDelete();
            $t->date('start_date');
            $t->decimal('total_amount', 15, 2);
            $t->decimal('down_payment', 15, 2)->default(0);
            $t->decimal('interest_percent', 5, 2)->default(0);
            $t->unsignedInteger('installments_count');
            $t->string('period')->default('monthly'); // weekly, monthly
            $t->string('status')->default('active'); // active, completed, cancelled
            $t->string('guarantor_name')->nullable();
            $t->string('guarantor_phone')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
        });
        Schema::create('installments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('installment_plan_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('sequence');
            $t->date('due_date');
            $t->decimal('amount', 15, 2);
            $t->decimal('paid_amount', 15, 2)->default(0);
            $t->date('paid_at')->nullable();
            $t->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['installments', 'installment_plans', 'stock_adjustment_items', 'stock_adjustments', 'stock_transfer_items', 'stock_transfers', 'expenses', 'expense_categories', 'treasury_transfers', 'payments'] as $t) {
            Schema::dropIfExists($t);
        }
        foreach (array_reverse(array_keys($this->documents)) as $table) {
            Schema::dropIfExists(str($table)->singular().'_items');
            Schema::dropIfExists($table);
        }
    }
};
