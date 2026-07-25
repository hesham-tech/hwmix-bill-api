<?php
// إنشاء جدول إعدادات أجهزة كاش هونكس.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hwnix_cash_device_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sms_device_id')
                ->constrained('hwnix_cash_devices')
                ->onDelete('cascade');

            $table->integer('heartbeat_interval_seconds')->default(60);
            $table->integer('max_retry_attempts')->default(3);
            $table->boolean('is_active')->default(true);
            $table->integer('version')->default(1);
            $table->json('custom_config')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hwnix_cash_device_settings');
    }
};
