<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 5)->default('ar')->after('role');
        });

        Schema::table('members', function (Blueprint $table) {
            $table->string('password')->nullable()->after('email');
            $table->string('locale', 5)->default('ar')->after('password');
            $table->timestamp('last_app_login_at')->nullable()->after('locale');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('locale'));
        Schema::table('members', fn (Blueprint $table) => $table->dropColumn(['password', 'locale', 'last_app_login_at']));
    }
};
