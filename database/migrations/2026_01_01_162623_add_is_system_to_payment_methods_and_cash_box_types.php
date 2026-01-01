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
        // إضافة حقل is_system لجدول payment_methods
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->boolean('is_system')->default(false)->after('active');
        });

        // إضافة حقل is_system لجدول cash_box_types
        Schema::table('cash_box_types', function (Blueprint $table) {
            $table->boolean('is_system')->default(false)->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn('is_system');
        });

        Schema::table('cash_box_types', function (Blueprint $table) {
            $table->dropColumn('is_system');
        });
    }
};
