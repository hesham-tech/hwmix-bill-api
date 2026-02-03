<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. ترحيل البيانات: نقل نوع العميل من جدول users إلى جدول company_user
        // نستخدم chunk لضمان عدم استهلاك الذاكرة إذا كان عدد المستخدمين كبيراً
        \Illuminate\Support\Facades\DB::table('users')
            ->whereNotNull('customer_type')
            ->orderBy('id')
            ->chunk(100, function ($users) {
                foreach ($users as $user) {
                    \Illuminate\Support\Facades\DB::table('company_user')
                        ->where('user_id', $user->id)
                        ->update(['customer_type_in_company' => $user->customer_type]);
                }
            });

        // 2. حذف الحقول بعد التأكد من نقل البيانات
        Schema::table('users', function (Blueprint $table) {
            // Removing redundant fields from global users table
            if (Schema::hasColumn('users', 'balance') || Schema::hasColumn('users', 'customer_type')) {
                $table->dropColumn(array_filter(['balance', 'customer_type'], fn($col) => Schema::hasColumn('users', $col)));
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('balance', 10, 2)->default(0);
            $table->string('customer_type')->default('retail')->comment('retail or wholesale');
        });
    }
};
