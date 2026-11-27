<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->timestamps();
        });

        Schema::create('employees', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('name');
            $t->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('job_title')->nullable();
            $t->string('national_id')->nullable();
            $t->string('nationality')->nullable();
            $t->string('phone')->nullable();
            $t->string('email')->nullable();
            $t->date('hire_date')->nullable();
            $t->decimal('basic_salary', 15, 2)->default(0);
            $t->decimal('housing_allowance', 15, 2)->default(0);
            $t->decimal('transport_allowance', 15, 2)->default(0);
            $t->decimal('other_allowances', 15, 2)->default(0);
            $t->decimal('commission_percent', 5, 2)->default(0);
            $t->string('iban')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('attendances', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $t->date('date');
            $t->time('check_in')->nullable();
            $t->time('check_out')->nullable();
            $t->string('status')->default('present'); // present, absent, late, leave
            $t->string('notes')->nullable();
            $t->unique(['employee_id', 'date']);
            $t->timestamps();
        });

        Schema::create('leave_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $t->string('type')->default('annual'); // annual, sick, unpaid, other
            $t->date('from_date');
            $t->date('to_date');
            $t->string('status')->default('pending'); // pending, approved, rejected
            $t->string('reason')->nullable();
            $t->timestamps();
        });

        Schema::create('salary_advances', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $t->foreignId('treasury_id')->constrained()->restrictOnDelete();
            $t->date('date');
            $t->decimal('amount', 15, 2);
            $t->decimal('monthly_deduction', 15, 2)->default(0);
            $t->decimal('deducted_amount', 15, 2)->default(0);
            $t->string('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('payrolls', function (Blueprint $t) {
            $t->id();
            $t->string('number')->unique();
            $t->string('month'); // 2026-11
            $t->foreignId('treasury_id')->nullable()->constrained()->nullOnDelete();
            $t->string('status')->default('draft'); // draft, posted
            $t->decimal('total', 15, 2)->default(0);
            $t->timestamp('posted_at')->nullable();
            $t->timestamps();
        });
        Schema::create('payroll_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payroll_id')->constrained()->cascadeOnDelete();
            $t->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $t->decimal('basic_salary', 15, 2)->default(0);
            $t->decimal('allowances', 15, 2)->default(0);
            $t->decimal('commissions', 15, 2)->default(0);
            $t->decimal('bonuses', 15, 2)->default(0);
            $t->decimal('deductions', 15, 2)->default(0);
            $t->decimal('advance_deduction', 15, 2)->default(0);
            $t->decimal('net_salary', 15, 2)->default(0);
            $t->timestamps();
        });

        // CRM - متابعة العملاء والمواعيد
        Schema::create('crm_activities', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('type')->default('call'); // call, meeting, visit, email, task
            $t->string('subject');
            $t->dateTime('scheduled_at')->nullable();
            $t->string('status')->default('pending'); // pending, done, cancelled
            $t->text('notes')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['crm_activities', 'payroll_items', 'payrolls', 'salary_advances', 'leave_requests', 'attendances', 'employees', 'departments'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
