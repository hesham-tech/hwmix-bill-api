<?php

//   هجرة تعديل جدول اشتراكات الشركات لإضافة خيار التجديد التلقائي للاشتراك.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('company_subscriptions', function (Blueprint $table) {
            $table->boolean('auto_renew')->default(true)->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_subscriptions', function (Blueprint $table) {
            $table->dropColumn('auto_renew');
        });
    }
};
