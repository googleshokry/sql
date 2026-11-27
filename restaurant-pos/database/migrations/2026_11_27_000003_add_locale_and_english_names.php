<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 5)->nullable()->after('role');
        });
        // الاسم بالإنجليزي اختياري — لو فاضي بيظهر الاسم العربي
        Schema::table('categories', function (Blueprint $table) {
            $table->string('name_en')->nullable()->after('name');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->string('name_en')->nullable()->after('name');
        });
        Schema::table('delivery_areas', function (Blueprint $table) {
            $table->string('name_en')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_areas', fn (Blueprint $table) => $table->dropColumn('name_en'));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('name_en'));
        Schema::table('categories', fn (Blueprint $table) => $table->dropColumn('name_en'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('locale'));
    }
};
