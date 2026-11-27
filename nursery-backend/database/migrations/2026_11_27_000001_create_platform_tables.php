<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->jsonb('name');
            $table->unsignedInteger('max_children')->nullable();
            $table->unsignedInteger('max_branches')->nullable();
            $table->decimal('price_monthly', 10, 2)->default(0);
            $table->jsonb('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('nurseries', function (Blueprint $table) {
            $table->id();
            $table->jsonb('name');
            $table->string('slug')->unique();
            $table->string('logo_path')->nullable();
            $table->string('default_locale', 5)->default('ar');
            $table->string('timezone')->default('Africa/Cairo');
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('subscription_ends_at')->nullable();
            $table->string('status')->default('active'); // active | suspended
            $table->timestamps();
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nursery_id')->constrained()->cascadeOnDelete();
            $table->jsonb('name');
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->unique()->after('email');
            $table->string('locale', 5)->default('ar')->after('phone');
            $table->string('avatar_path')->nullable()->after('locale');
            $table->boolean('is_super_admin')->default(false)->after('avatar_path');
        });

        Schema::create('nursery_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nursery_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['nursery_id', 'user_id']);
        });

        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token')->unique();
            $table->string('platform', 10); // android | ios
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
        Schema::dropIfExists('nursery_user');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'locale', 'avatar_path', 'is_super_admin']);
        });
        Schema::dropIfExists('branches');
        Schema::dropIfExists('nurseries');
        Schema::dropIfExists('plans');
    }
};
