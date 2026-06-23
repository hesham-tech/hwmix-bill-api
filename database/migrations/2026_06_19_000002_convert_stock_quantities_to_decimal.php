<?php
// ميجريشن لتحويل حقول الكمية في جدول المخزون من integer إلى decimal لدعم الكسور العشرية في المنتجات المقاسة بالوزن والطول
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            // تحويل من integer إلى decimal(18,6) لدعم الكسور
            $table->decimal('quantity', 18, 6)->default(0)->change();
            $table->decimal('reserved', 18, 6)->default(0)->change();
            $table->decimal('min_quantity', 18, 6)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            // العودة لـ integer (قد يفقد بيانات الكسور)
            $table->integer('quantity')->default(0)->change();
            $table->integer('reserved')->default(0)->change();
            $table->integer('min_quantity')->default(0)->change();
        });
    }
};
