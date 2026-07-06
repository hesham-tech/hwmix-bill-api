<?php
// إضافة عمود hardware_name لحفظ اسم عتاد الجهاز الفعلي إلى جانب الاسم المستعار.

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
        Schema::table('smsgate_devices', function (Blueprint $table) {
            $table->string('hardware_name')->nullable()->after('device_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('smsgate_devices', function (Blueprint $table) {
            $table->dropColumn('hardware_name');
        });
    }
};
