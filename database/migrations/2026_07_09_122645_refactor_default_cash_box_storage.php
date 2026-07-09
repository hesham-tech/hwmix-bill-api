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

        // 2. ترحيل البيانات الحالية من cash_boxes.is_default
        $defaultBoxes = Illuminate\Support\Facades\DB::table('cash_boxes')
            ->where('is_default', true)
            ->whereNotNull('user_id')
            ->get();

        foreach ($defaultBoxes as $box) {
            Illuminate\Support\Facades\DB::table('users')
                ->where('id', $box->user_id)
                ->update(['default_cash_box_id' => $box->id]);
        }

        // 3. إسقاط عمود is_default من جدول cash_boxes
        Schema::table('cash_boxes', function (Blueprint $table) {
            if (Schema::hasColumn('cash_boxes', 'is_default')) {
                $table->dropColumn('is_default');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_boxes', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('company_id');
        });

        // استعادة البيانات الحالية
        $users = Illuminate\Support\Facades\DB::table('users')
            ->whereNotNull('default_cash_box_id')
            ->get();

        foreach ($users as $user) {
            Illuminate\Support\Facades\DB::table('cash_boxes')
                ->where('id', $user->default_cash_box_id)
                ->update(['is_default' => true]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['default_cash_box_id']);
            $table->dropColumn('default_cash_box_id');
        });
    }
};
