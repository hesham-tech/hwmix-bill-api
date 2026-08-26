<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('revenues', function (Blueprint $table) {
            $table->uuid('financial_operation_id')->nullable()->after('note');
            $table->string('status')->default('completed')->after('financial_operation_id');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('revenues', function (Blueprint $table) {
            $table->dropColumn(['financial_operation_id', 'status']);
            $table->dropSoftDeletes();
        });
    }
};
