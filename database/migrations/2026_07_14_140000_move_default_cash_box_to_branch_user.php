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
        // 1. إضافة الأعمدة الجديدة لجدول branch_user
        Schema::table('branch_user', function (Blueprint $table) {
            $table->unsignedBigInteger('default_cash_box_id')->nullable()->after('branch_id');
            $table->unsignedBigInteger('default_warehouse_id')->nullable()->after('default_cash_box_id');

            // إضافة القيود البرمجية للمفاتيح الأجنبية
            $table->foreign('default_cash_box_id')
                ->references('id')
                ->on('cash_boxes')
                ->nullOnDelete();

            $table->foreign('default_warehouse_id')
                ->references('id')
                ->on('warehouses')
                ->nullOnDelete();
        });

        // 2. نقل تفضيلات الخزن الافتراضية الحالية من جدول users إلى جدول branch_user
        $users = DB::table('users')->whereNotNull('default_cash_box_id')->get();

        foreach ($users as $user) {
            // تحديد الفرع المستهدف
            $branchId = $user->branch_id;

            // إذا لم يكن للمستخدم فرع افتراضي معين، نحاول معرفة الفرع من الخزنة الافتراضية نفسها
            if (!$branchId) {
                $branchId = DB::table('cash_boxes')
                    ->where('id', $user->default_cash_box_id)
                    ->value('branch_id');
            }

            if ($branchId) {
                // التحقق مما إذا كان هناك سجل ارتباط حالي للمستخدم بهذا الفرع
                $exists = DB::table('branch_user')
                    ->where('user_id', $user->id)
                    ->where('branch_id', $branchId)
                    ->exists();

                if ($exists) {
                    DB::table('branch_user')
                        ->where('user_id', $user->id)
                        ->where('branch_id', $branchId)
                        ->update([
                            'default_cash_box_id' => $user->default_cash_box_id,
                            'updated_at' => now(),
                        ]);
                } else {
                    DB::table('branch_user')->insert([
                        'user_id' => $user->id,
                        'branch_id' => $branchId,
                        'default_cash_box_id' => $user->default_cash_box_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // إزالة المفاتيح الأجنبية والأعمدة من branch_user
        Schema::table('branch_user', function (Blueprint $table) {
            $table->dropForeign(['default_cash_box_id']);
            $table->dropForeign(['default_warehouse_id']);
            $table->dropColumn(['default_cash_box_id', 'default_warehouse_id']);
        });
    }
};
