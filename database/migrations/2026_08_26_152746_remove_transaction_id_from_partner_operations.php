<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('partner_operations', 'transaction_id')) {
            Schema::table('partner_operations', function (Blueprint $table) {
                $table->dropForeign(['transaction_id']);
                $table->dropColumn('transaction_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('partner_operations', function (Blueprint $table) {
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->onDelete('set null');
        });
    }
};