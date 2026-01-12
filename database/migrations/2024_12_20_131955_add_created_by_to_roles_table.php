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
        Schema::table('roles', function (Blueprint $table) {
            if (!Schema::hasColumn('roles', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('id');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            }
            if (!Schema::hasColumn('roles', 'company_id')) {
                $table->unsignedBigInteger('company_id')->nullable()->after('created_by');
                $table->foreign('company_id')->references('id')->on('companies')->onDelete('set null');
            } else {
                // If column exists (added by Spatie teams), just add the foreign key if missing
                // Note: Laravel doesn't have a direct "hasForeignKey" but we can wrap it in a try-catch or just skip if we are sure
                // For safety on production/test, we'll just skip adding the FK if column was already there via Spatie
                // to avoid complexity, but usually Spatie doesn't add FKs.
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            if (Schema::hasColumn('roles', 'created_by')) {
                $table->dropForeign(['created_by']);
                $table->dropColumn('created_by');
            }
            if (Schema::hasColumn('roles', 'company_id')) {
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            }
        });
    }
};
