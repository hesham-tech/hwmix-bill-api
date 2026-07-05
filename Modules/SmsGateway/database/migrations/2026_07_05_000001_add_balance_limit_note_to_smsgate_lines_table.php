<?php
// إضافة حقول الرصيد والرصيد الفعلي والليمت والملاحظات لجدول الشرائح.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('smsgate_lines', function (Blueprint $table) {
            $table->decimal('balance', 18, 2)->default(0.00)->after('status');
            $table->decimal('actual_balance', 18, 2)->default(0.00)->after('balance');
            $table->integer('daily_limit')->default(0)->after('actual_balance');
            $table->text('note')->nullable()->after('daily_limit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('smsgate_lines', function (Blueprint $table) {
            $table->dropColumn(['balance', 'actual_balance', 'daily_limit', 'note']);
        });
    }
};
