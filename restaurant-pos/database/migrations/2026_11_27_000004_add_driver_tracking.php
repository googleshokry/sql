<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // آخر موقع للطيار + حالة الشيفت
        Schema::table('drivers', function (Blueprint $table) {
            $table->boolean('is_online')->default(false)->after('is_active');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->float('location_accuracy')->nullable();
            $table->timestamp('location_updated_at')->nullable();
        });

        // سجل المواقع (خط سير الطيار)
        Schema::create('driver_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->float('accuracy')->nullable();
            $table->float('speed')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['driver_id', 'created_at']);
        });

        // مراحل التوصيل + موقع العميل
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('delivery_lat', 10, 7)->nullable()->after('delivery_address');
            $table->decimal('delivery_lng', 10, 7)->nullable()->after('delivery_lat');
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('driver_note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['delivery_lat', 'delivery_lng', 'picked_up_at', 'arrived_at', 'delivered_at', 'driver_note']);
        });
        Schema::dropIfExists('driver_locations');
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn(['is_online', 'latitude', 'longitude', 'location_accuracy', 'location_updated_at']);
        });
    }
};
