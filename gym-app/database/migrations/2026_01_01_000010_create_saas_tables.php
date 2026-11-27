<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Plans the SaaS sells to gyms (not to be confused with `plans`, which gyms sell to members).
        Schema::create('saas_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->decimal('price_monthly', 10, 2)->default(0);
            $table->decimal('price_yearly', 10, 2)->default(0);
            $table->string('currency', 10)->default('EGP');
            $table->unsignedInteger('max_members')->nullable()->comment('null = unlimited');
            $table->unsignedInteger('max_staff')->nullable();
            $table->unsignedInteger('max_branches')->default(1);
            $table->boolean('mobile_app')->default(true);
            $table->boolean('store_module')->default(true);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        // Tenants.
        Schema::create('gyms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('logo')->nullable();
            $table->string('currency', 10)->default('ج.م');
            $table->string('locale', 5)->default('ar');
            $table->foreignId('saas_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('billing_cycle')->default('monthly');
            $table->string('status')->default('trial')->index(); // trial, active, past_due, suspended, cancelled
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('subscription_ends_at')->nullable();
            $table->string('suspended_reason')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('gym_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('receptionist');
            $table->timestamps();
            $table->unique(['gym_id', 'user_id']);
        });

        // What gyms pay the SaaS.
        Schema::create('gym_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('saas_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 10)->default('EGP');
            $table->string('billing_cycle')->default('monthly');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status')->default('unpaid')->index(); // unpaid, paid, void
            $table->timestamp('paid_at')->nullable();
            $table->string('method')->nullable();
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gym_invoices');
        Schema::dropIfExists('gym_user');
        Schema::dropIfExists('gyms');
        Schema::dropIfExists('saas_plans');
    }
};
