<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Repair Primary Key and Auto Increment for 'id'
        try {
            DB::statement('ALTER TABLE products MODIFY id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY');
        } catch (\Exception $e) {
            try {
                DB::statement('ALTER TABLE products MODIFY id BIGINT UNSIGNED AUTO_INCREMENT');
            } catch (\Exception $ex) {
                // Ignore
            }
        }

        // 2. Restore Unique Slug index if missing
        try {
            DB::statement('ALTER TABLE products ADD UNIQUE products_slug_unique (slug)');
        } catch (\Exception $e) {
            // Ignore if index already exists
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No easy way to reverse this safely without potentially breaking relations
    }
};
