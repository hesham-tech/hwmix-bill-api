<?php
// إنشاء جدول أوامر الأجهزة الموجهة لهواتف كاش هونكس.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hwnix_cash_device_commands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sms_device_id')
                ->constrained('hwnix_cash_devices')
                ->onDelete('cascade');

            $table->string('command_type', 50)->index();
            $table->json('payload')->nullable();
            $table->string('status', 30)->default('pending')->index();
            $table->json('response_payload')->nullable();
            $table->string('idempotency_key', 100)->nullable()->unique();
            $table->timestamp('executed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hwnix_cash_device_commands');
    }
};
