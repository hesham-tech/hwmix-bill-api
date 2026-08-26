<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// إضافة رابط العملية المالية وحقل الحالة لجدول المدفوعات لدعم العكس المالي الآمن
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'financial_operation_id')) {
                $table->string('financial_operation_id', 36)->nullable()->after('cash_box_id')
                    ->comment('UUID رابط للعملية المالية الحاكمة في financial_operations');
            }

            if (!Schema::hasColumn('payments', 'status')) {
                $table->string('status')->default('completed')->after('financial_operation_id')
                    ->comment('حالة الدفعة: completed أو reversed');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'financial_operation_id')) {
                $table->dropColumn('financial_operation_id');
            }
            if (Schema::hasColumn('payments', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
