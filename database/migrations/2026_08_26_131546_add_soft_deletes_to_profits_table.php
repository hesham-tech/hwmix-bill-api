<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profits', function (Blueprint $table) {
            $table->string('status')->default('completed')->after('note');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('profits', function (Blueprint $table) {
            $table->dropColumn('status');
            $table->dropSoftDeletes();
        });
    }
};
