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
        Schema::table('cash_box_types', function (Blueprint $table) {
            // Drop the old global unique constraint on name
            $table->dropUnique(['name']);

            // Add a new unique constraint on name and company_id
            $table->unique(['name', 'company_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_box_types', function (Blueprint $table) {
            $table->dropUnique(['name', 'company_id']);
            $table->unique(['name']);
        });
    }
};
