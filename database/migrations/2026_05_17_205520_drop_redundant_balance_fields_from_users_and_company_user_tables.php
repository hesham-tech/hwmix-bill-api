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
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'balance')) {
                $table->dropColumn('balance');
            }
        });

        Schema::table('company_user', function (Blueprint $table) {
            if (Schema::hasColumn('company_user', 'balance_in_company')) {
                $table->dropColumn('balance_in_company');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'balance')) {
                $table->decimal('balance', 10, 2)->default(0);
            }
        });

        Schema::table('company_user', function (Blueprint $table) {
            if (!Schema::hasColumn('company_user', 'balance_in_company')) {
                $table->decimal('balance_in_company', 15, 2)->default(0)->comment('رصيد العميل في هذه الشركة');
            }
        });
    }
};
