<?php
// إنشاء جدول أجهزة كاش هونكس المسجلة في الخادم.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hwnix_cash_devices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('created_by')->index();

            $table->string('android_id', 100)->unique();
            $table->string('uuid', 100)->nullable();
            $table->string('device_name', 150);
            $table->string('brand', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('android_version', 50)->nullable();
            $table->string('app_version', 50)->nullable();
            $table->string('fcm_token')->nullable();

            $table->json('capabilities')->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->timestamp('last_seen_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hwnix_cash_devices');
    }
};
