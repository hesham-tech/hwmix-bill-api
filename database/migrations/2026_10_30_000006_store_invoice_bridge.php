<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('invoices',function(Blueprint $t) {
            $t->boolean('is_marketplace_managed')->default(false)->index();
            $t->foreignId('store_sub_order_id')->nullable()->unique()->constrained('store_sub_orders');
        });
        Schema::table('store_stock_allocations',function(Blueprint $t) {
            $t->decimal('unit_cost_snapshot',18,6)->nullable();
            $t->decimal('cost_total_snapshot',24,8)->nullable();
        });
        Schema::table('store_returns',function(Blueprint $t) { $t->uuid('erp_operation_id')->nullable(); });
        Schema::table('store_payouts',function(Blueprint $t) { $t->uuid('erp_operation_id')->nullable(); });
    }
    public function down(): void {
        Schema::table('store_payouts',fn(Blueprint $t)=>$t->dropColumn('erp_operation_id'));
        Schema::table('store_returns',fn(Blueprint $t)=>$t->dropColumn('erp_operation_id'));
        Schema::table('store_stock_allocations',fn(Blueprint $t)=>$t->dropColumn(['unit_cost_snapshot','cost_total_snapshot']));
        Schema::table('invoices',function(Blueprint $t) { $t->dropForeign(['store_sub_order_id']); $t->dropColumn(['is_marketplace_managed','store_sub_order_id']); });
    }
};
