<?php

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
        // 1. إضافة حقل default_cash_box_id لجدول المستخدمين
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('default_cash_box_id')->nullable()->constrained('cash_boxes')->nullOnDelete();
        });

        // 2. ترحيل البيانات الحالية من cash_boxes.is_default لـ users.default_cash_box_id
        $defaultBoxes = Illuminate\Support\Facades\DB::table('cash_boxes')
            ->where('is_default', true)
            ->whereNotNull('user_id')
            ->get();

        foreach ($defaultBoxes as $box) {
            Illuminate\Support\Facades\DB::table('users')
                ->where('id', $box->user_id)
                ->update(['default_cash_box_id' => $box->id]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['default_cash_box_id']);
            $table->dropColumn('default_cash_box_id');
        });
    }
};
