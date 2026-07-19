<?php
// ترحيل لتحديث معمارية قاعدة البيانات بجعل معرف الأندرويد فريداً ومفتاحاً أجنبياً للخطوط.

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
        // 1. جعل حقل android_id فريداً في جدول الأجهزة
        Schema::table('smsgate_devices', function (Blueprint $table) {
            // حذف الفهرس العادي القديم أولاً لتجنب التكرار
            $table->dropIndex('smsgate_devices_android_id_index');
            $table->string('android_id')->unique()->change();
        });

        // 2. إضافة العمود الجديد مؤقتاً كـ nullable لترحيل البيانات
        Schema::table('smsgate_lines', function (Blueprint $table) {
            $table->string('device_android_id')->nullable()->after('id')->index();
        });

        // 3. ترحيل البيانات القديمة بربطها بمعرف الأندرويد للخطوط الحالية
        \DB::table('smsgate_lines')
            ->join('smsgate_devices', 'smsgate_lines.sms_device_id', '=', 'smsgate_devices.id')
            ->update(['smsgate_lines.device_android_id' => \DB::raw('smsgate_devices.android_id')]);

        // 4. حذف قيد المفتاح الأجنبي القديم والعمود القديم، وتعيين العمود الجديد كـ NOT NULL ومفتاح أجنبي
        Schema::table('smsgate_lines', function (Blueprint $table) {
            $table->dropForeign('smsgate_lines_sms_device_id_foreign');
            $table->dropColumn('sms_device_id');
            
            $table->string('device_android_id')->nullable(false)->change();
            $table->foreign('device_android_id')
                ->references('android_id')
                ->on('smsgate_devices')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('smsgate_lines', function (Blueprint $table) {
            $table->dropForeign(['device_android_id']);
            
            $table->unsignedBigInteger('sms_device_id')->nullable()->after('id');
        });

        \DB::table('smsgate_lines')
            ->join('smsgate_devices', 'smsgate_lines.device_android_id', '=', 'smsgate_devices.android_id')
            ->update(['smsgate_lines.sms_device_id' => \DB::raw('smsgate_devices.id')]);

        Schema::table('smsgate_lines', function (Blueprint $table) {
            $table->dropColumn('device_android_id');
            
            $table->unsignedBigInteger('sms_device_id')->nullable(false)->change();
            $table->foreign('sms_device_id')
                ->references('id')
                ->on('smsgate_devices')
                ->onDelete('cascade');
        });

        Schema::table('smsgate_devices', function (Blueprint $table) {
            $table->dropUnique('smsgate_devices_android_id_unique');
            $table->string('android_id')->index()->change();
        });
    }
};
