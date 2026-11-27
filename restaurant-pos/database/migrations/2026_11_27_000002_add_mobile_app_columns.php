<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // pos = شاشة الكاشير، staff_app = تطبيق الكاشير، customer_app = تطبيق العملاء
            $table->string('source')->default('pos')->after('status');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('password')->nullable()->after('phone2');
        });

        Schema::table('drivers', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->unique()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('password');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
