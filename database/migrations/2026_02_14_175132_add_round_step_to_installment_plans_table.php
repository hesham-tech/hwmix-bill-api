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
            if (!Schema::hasColumn('installment_plans', 'round_step')) {
                $table->integer('round_step')->nullable()->after('installment_amount')->comment('خطوة التقريب');
            }
        });
    }

    public function down(): void
    {
        Schema::table('installment_plans', function (Blueprint $table) {
            if (Schema::hasColumn('installment_plans', 'round_step')) {
                $table->dropColumn('round_step');
            }
        });
    }
};
