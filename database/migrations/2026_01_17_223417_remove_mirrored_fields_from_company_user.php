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
            $table->dropColumn([
                'user_phone',
                'phone_in_company',
                'user_email',
                'email_in_company',
                'user_username'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_user', function (Blueprint $table) {
            $table->string('user_phone')->nullable();
            $table->string('phone_in_company')->nullable();
            $table->string('user_email')->nullable();
            $table->string('email_in_company')->nullable();
            $table->string('user_username')->nullable();
        });
    }
};
