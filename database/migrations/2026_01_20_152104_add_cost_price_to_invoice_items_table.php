محتاج
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
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->decimal('cost_price', 15, 2)->nullable()->after('unit_price')->comment('سعر التكلفة وقت البيع');
            $table->decimal('total_cost', 15, 2)->nullable()->after('subtotal')->comment('إجمالي التكلفة (الكمية × سعر التكلفة)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn(['cost_price', 'total_cost']);
        });
    }
};
