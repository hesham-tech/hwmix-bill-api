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
        Schema::table('attribute_values', function (Blueprint $table) {
            if (!Schema::hasColumn('attribute_values', 'company_id')) {
                $table->foreignId('company_id')->after('attribute_id')->nullable()->constrained()->onDelete('cascade');
            }
            if (!Schema::hasColumn('attribute_values', 'updated_by')) {
                $table->foreignId('updated_by')->after('created_by')->nullable()->constrained('users')->onDelete('cascade');
            }
            if (!Schema::hasColumn('attribute_values', 'deleted_by')) {
                $table->foreignId('deleted_by')->after('updated_by')->nullable()->constrained('users')->onDelete('cascade');
            }
            if (!Schema::hasColumn('attribute_values', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attribute_values', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
            $table->dropForeign(['updated_by']);
            $table->dropColumn('updated_by');
            $table->dropForeign(['deleted_by']);
            $table->dropColumn('deleted_by');
            $table->dropSoftDeletes();
        });
    }
};
