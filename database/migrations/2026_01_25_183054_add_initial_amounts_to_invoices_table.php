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
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('initial_paid_amount', 15, 2)->default(0)->after('paid_amount')->comment('المبلغ المدفوع لحظة إنشاء الفاتورة');
            $table->decimal('initial_remaining_amount', 15, 2)->default(0)->after('remaining_amount')->comment('المبلغ المتبقي لحظة إنشاء الفاتورة');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['initial_paid_amount', 'initial_remaining_amount']);
        });
    }
};
