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
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'first_name')) {
                $table->string('first_name')->nullable()->after('password');
            }
            if (!Schema::hasColumn('users', 'last_name')) {
                $table->string('last_name')->nullable()->after('first_name');
            }
        });

        Schema::table('company_user', function (Blueprint $table) {
            if (!Schema::hasColumn('company_user', 'first_name_in_company')) {
                $table->string('first_name_in_company')->nullable()->after('created_by');
            }
            if (!Schema::hasColumn('company_user', 'last_name_in_company')) {
                $table->string('last_name_in_company')->nullable()->after('first_name_in_company');
            }
        });

        // Data migration: Split full_name into first and last parts
        \App\Models\User::all()->each(function ($user) {
            $parts = explode(' ', trim($user->full_name), 2);
            $user->update([
                'first_name' => $parts[0] ?? null,
                'last_name' => $parts[1] ?? null,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name']);
        });

        Schema::table('company_user', function (Blueprint $table) {
            $table->dropColumn(['first_name_in_company', 'last_name_in_company']);
        });
    }
};
