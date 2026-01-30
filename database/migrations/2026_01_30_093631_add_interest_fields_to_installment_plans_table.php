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
        Schema::table('installment_plans', function (Blueprint $table) {
            $table->decimal('interest_rate', 5, 2)->default(0)->after('remaining_amount');
            $table->decimal('interest_amount', 15, 2)->default(0)->after('interest_rate');
            $table->decimal('total_amount', 15, 2)->default(0)->after('interest_amount'); // net_amount + interest_amount
            $table->string('frequency')->default('monthly')->after('number_of_installments');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('installment_plans', function (Blueprint $table) {
            $table->dropColumn(['interest_rate', 'interest_amount', 'total_amount', 'frequency']);
        });
    }
};
