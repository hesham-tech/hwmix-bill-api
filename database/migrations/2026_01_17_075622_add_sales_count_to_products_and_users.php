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
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('sales_count')->default(0)->after('featured');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->unsignedInteger('sales_count')->default(0)->after('status');
        });

        Schema::table('company_user', function (Blueprint $table) {
            $table->unsignedInteger('sales_count')->default(0)->after('customer_type_in_company');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('sales_count');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn('sales_count');
        });

        Schema::table('company_user', function (Blueprint $table) {
            $table->dropColumn('sales_count');
        });
    }
};
