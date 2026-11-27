<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['customers', 'suppliers'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name) {
                $table->id();
                $table->string('code')->unique();
                $table->string('name');
                $table->string('company_name')->nullable();
                $table->string('phone')->nullable();
                $table->string('mobile')->nullable();
                $table->string('email')->nullable();
                $table->string('tax_number')->nullable();
                $table->string('commercial_register')->nullable();
                $table->string('city')->nullable();
                $table->string('address')->nullable();
                $table->decimal('opening_balance', 15, 2)->default(0);
                if ($name === 'customers') {
                    $table->decimal('credit_limit', 15, 2)->default(0);
                    $table->string('price_list')->default('retail'); // retail, wholesale
                }
                $table->text('notes')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('short_name')->nullable();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('barcode')->nullable()->unique();
            $table->string('name');
            $table->string('type')->default('product'); // product, service
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('purchase_price', 15, 2)->default(0);
            $table->decimal('cost_price', 15, 4)->default(0); // متوسط التكلفة المرجح
            $table->decimal('sale_price', 15, 2)->default(0);
            $table->decimal('wholesale_price', 15, 2)->default(0);
            $table->decimal('min_sale_price', 15, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(15);
            $table->boolean('price_includes_tax')->default(false);
            $table->decimal('min_stock', 15, 3)->default(0);
            $table->boolean('track_stock')->default(true);
            $table->string('image')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('product_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 15, 3)->default(0);
            $table->unique(['product_id', 'warehouse_id']);
            $table->timestamps();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('type'); // opening, purchase, sale, sale_return, purchase_return, transfer_in, transfer_out, adjustment
            $table->decimal('quantity', 15, 3); // + in / - out
            $table->decimal('unit_cost', 15, 4)->default(0);
            $table->decimal('balance_after', 15, 3)->default(0);
            $table->nullableMorphs('reference');
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('product_stocks');
        Schema::dropIfExists('products');
        Schema::dropIfExists('units');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('customers');
    }
};
