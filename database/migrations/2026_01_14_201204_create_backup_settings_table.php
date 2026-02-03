<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('backup_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string'); // string, integer, boolean, json
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // Seed default settings
        DB::table('backup_settings')->insert([
            ['key' => 'backup_frequency', 'value' => 'daily', 'type' => 'string', 'description' => 'Frequency of automatic backups (daily, weekly, monthly)'],
            ['key' => 'backup_retention_days', 'value' => '7', 'type' => 'integer', 'description' => 'Number of days to keep backups'],
            ['key' => 'backup_include_files', 'value' => '0', 'type' => 'boolean', 'description' => 'Whether to include files in the backup'],
            ['key' => 'backup_time', 'value' => '03:00', 'type' => 'string', 'description' => 'Time of day to run the backup'],
            ['key' => 'backup_restore_token', 'value' => Str::random(32), 'type' => 'string', 'description' => 'Secondary token for sensitive restore operations'],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backup_settings');
    }
};
