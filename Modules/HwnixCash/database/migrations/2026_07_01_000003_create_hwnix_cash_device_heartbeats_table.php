<?php
// إنشاء جدول سجلات نبضات التشغيل لأجهزة كاش هونكس.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hwnix_cash_device_heartbeats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sms_device_id')
                ->constrained('hwnix_cash_devices')
                ->onDelete('cascade');

            $table->string('network_type', 50)->nullable();
            $table->integer('battery_level')->nullable();
            $table->boolean('is_internet_available')->default(true);
            $table->bigInteger('free_memory_bytes')->nullable();
            $table->bigInteger('free_storage_bytes')->nullable();
            $table->string('app_version', 50)->nullable();
            $table->integer('configuration_version')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hwnix_cash_device_heartbeats');
    }
};
