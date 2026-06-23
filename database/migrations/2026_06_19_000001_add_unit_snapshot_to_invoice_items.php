<?php
// ميجريشن لإضافة snapshot اسم الوحدة لبنود الفواتير لضمان بقاء بيانات الفاتورة كاملة حتى بعد حذف الوحدة
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            // snapshot اسم الوحدة وقت تحرير الفاتورة
            $table->string('unit_name_snapshot')->nullable()->after('unit_id');
        });

        // ملء البيانات الموجودة: نسخ اسم الوحدة الحالي للسجلات القديمة
        if (DB::table('invoice_items')->count() > 0) {
            DB::statement("
                UPDATE invoice_items
                SET unit_name_snapshot = (
                    SELECT name FROM units WHERE units.id = invoice_items.unit_id
                )
                WHERE unit_id IS NOT NULL AND unit_name_snapshot IS NULL
            ");
        }
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn('unit_name_snapshot');
        });
    }
};
