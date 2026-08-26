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
        Schema::table('installment_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('installment_payments', 'financial_operation_id')) {
                $table->uuid('financial_operation_id')->nullable()->after('id')->index();
            }
            if (!Schema::hasColumn('installment_payments', 'status')) {
                $table->string('status')->default('completed')->after('financial_operation_id')->index();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('installment_payments', function (Blueprint $table) {
            $table->dropColumn(['financial_operation_id', 'status']);
        });
    }
};
