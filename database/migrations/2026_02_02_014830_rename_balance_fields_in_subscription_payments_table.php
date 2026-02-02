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
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->renameColumn('balance_before', 'partial_payment_before');
            $table->renameColumn('balance_after', 'partial_payment_after');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->renameColumn('partial_payment_before', 'balance_before');
            $table->renameColumn('partial_payment_after', 'balance_after');
        });
    }
};
