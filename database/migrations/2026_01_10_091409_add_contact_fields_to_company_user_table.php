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
        Schema::table('company_user', function (Blueprint $table) {
            if (!Schema::hasColumn('company_user', 'phone_in_company')) {
                $table->string('phone_in_company')->nullable()->after('user_phone')->comment('رقم الهاتف الخاص بالتواصل داخل الشركة');
            }
            if (!Schema::hasColumn('company_user', 'email_in_company')) {
                $table->string('email_in_company')->nullable()->after('user_email')->comment('البريد الإلكتروني الخاص بالتواصل داخل الشركة');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_user', function (Blueprint $table) {
            $table->dropColumn(['phone_in_company', 'email_in_company']);
        });
    }
};
