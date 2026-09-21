<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_providers', function (Blueprint $table) {
            // category: 'wallet' (محفظة ذكية), 'machine' (ماكينة دفع), 'bank' (حساب بنكي)
            $table->string('category', 50)->default('wallet')->after('code');
        });
    }

    public function down(): void
    {
        Schema::table('service_providers', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
