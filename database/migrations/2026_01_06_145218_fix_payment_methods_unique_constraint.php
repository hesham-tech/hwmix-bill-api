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
        Schema::table('payment_methods', function (Blueprint $table) {
            // Drop old unique constraint
            // We use a try-catch because the index name might vary or it might not exist in some environments
            try {
                $table->dropUnique(['code']);
            } catch (\Exception $e) {
                // Fallback for specific index naming if the above fails
                try {
                    $table->dropUnique('payment_methods_code_unique');
                } catch (\Exception $e) {
                }
            }

            // Add new composite unique constraint
            $table->unique(['company_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'code']);
            $table->unique('code');
        });
    }
};
