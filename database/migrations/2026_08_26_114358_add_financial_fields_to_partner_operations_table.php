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
        Schema::table('partner_operations', function (Blueprint $table) {
            $table->string('financial_operation_id')->nullable()->after('transaction_id');
            $table->string('status')->default('completed')->after('financial_operation_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('partner_operations', function (Blueprint $table) {
            $table->dropColumn(['financial_operation_id', 'status']);
        });
    }
};
