<?php
// إنشاء جدول سجلات نبضات الأجهزة والمراقبة الحية للأداء.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('smsgate_device_heartbeats');
        Schema::create('smsgate_device_heartbeats', function (Blueprint $table) {
            $table->id();
            
            // الربط بالجهاز (علاقة رأس بأطراف One-to-Many)
            $table->foreignId('sms_device_id')->constrained('smsgate_devices')->onDelete('cascade');
            
            // قراءات الحالة والمراقبة
            $table->string('network_type')->nullable(); // wifi, cellular, none
            $table->integer('battery_level')->default(100);
            $table->boolean('is_internet_available')->default(true);
            
            $table->bigInteger('free_memory_bytes')->nullable();
            $table->bigInteger('free_storage_bytes')->nullable();
            $table->string('app_version');
            
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('smsgate_device_heartbeats');
    }
};
