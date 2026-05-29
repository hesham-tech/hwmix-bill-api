<?php

// تعليق عربي: هجرة تعديل جدول الخطط لإضافة قيود الحد الأقصى للمنتجات والفواتير.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->integer('max_products')->nullable()->after('max_users');
            $table->integer('max_invoices')->nullable()->after('max_products');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['max_products', 'max_invoices']);
        });
    }
};
