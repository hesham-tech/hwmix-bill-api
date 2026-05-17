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
        // 1. Change default values
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_active_in_store')->default(false)->change();
            $table->boolean('is_active_in_sales')->default(true)->change();
        });

        // 2. Update existing records
        \Illuminate\Support\Facades\DB::table('products')->update([
            'is_active_in_store' => false,
            'is_active_in_sales' => true,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_active_in_store')->default(true)->change();
            $table->boolean('is_active_in_sales')->default(true)->change();
        });
    }
};
