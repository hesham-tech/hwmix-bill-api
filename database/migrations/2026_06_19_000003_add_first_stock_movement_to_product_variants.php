<?php
// ميجريشن لإضافة دعم حماية الوحدة الأساسية ومنع تغييرها بعد وجود حركات مخزنية
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // إضافة حقل تاريخ أول حركة مخزنية لمنع تغيير الوحدة الأساسية بعدها
        Schema::table('product_variants', function (Blueprint $table) {
            $table->timestamp('first_stock_movement_at')
                  ->nullable()
                  ->after('display_unit_id')
                  ->comment('تاريخ أول حركة مخزنية — بعدها يُمنع تغيير base_unit_id');
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn('first_stock_movement_at');
        });
    }
};
