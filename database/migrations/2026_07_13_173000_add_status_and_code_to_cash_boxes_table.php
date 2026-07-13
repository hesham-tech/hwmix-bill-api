<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. إضافة الحقول الجديدة
        Schema::table('cash_boxes', function (Blueprint $table) {
            $table->string('code', 50)->nullable()->unique()->after('id');
            $table->string('status', 50)->default('active')->after('access_type')->index();
        });

        // 2. توليد أكواد فريدة للخزن الحالية لضمان عدم وجود قيم null
        $boxes = DB::table('cash_boxes')->orderBy('id', 'asc')->get();
        foreach ($boxes as $box) {
            $code = 'CBX-' . str_pad($box->id, 6, '0', STR_PAD_LEFT);
            DB::table('cash_boxes')->where('id', $box->id)->update(['code' => $code]);
        }

        // 3. ترحيل حالة الخزن الحالية بناءً على الحقول القديمة
        DB::table('cash_boxes')
            ->where('access_type', 'legacy_archived')
            ->update(['status' => 'archived']);

        // التحقق من الخزن غير النشطة ومزامنتها
        if (Schema::hasColumn('cash_boxes', 'is_active')) {
            DB::table('cash_boxes')
                ->where('is_active', false)
                ->where('access_type', '!=', 'legacy_archived')
                ->update(['status' => 'inactive']);

            // 4. إسقاط العمود المادي القديم
            Schema::table('cash_boxes', function (Blueprint $table) {
                $table->dropColumn('is_active');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_boxes', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('status');
        });

        DB::table('cash_boxes')
            ->where('status', 'inactive')
            ->update(['is_active' => false]);

        DB::table('cash_boxes')
            ->where('status', 'archived')
            ->update(['is_active' => false]);

        Schema::table('cash_boxes', function (Blueprint $table) {
            $table->dropColumn('status');
            $table->dropColumn('code');
        });
    }
};
