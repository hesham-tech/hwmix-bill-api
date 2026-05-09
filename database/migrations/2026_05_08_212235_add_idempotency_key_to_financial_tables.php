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
        $tables = ['transactions', 'invoices', 'expenses'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('idempotency_key')->nullable()->unique()->after('id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = ['transactions', 'invoices', 'expenses'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('idempotency_key');
            });
        }
    }
};
