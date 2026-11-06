<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('store_seller_profiles',fn(Blueprint $t)=>$t->boolean('policy_review_required')->default(false));
        Schema::table('store_returns',function(Blueprint $t) {
            $t->string('operation_key',64)->nullable()->unique();
            $t->foreignId('store_refund_id')->nullable()->constrained('store_refunds');
            $t->timestamp('received_at')->nullable(); $t->timestamp('completed_at')->nullable();
            $t->string('condition',30)->nullable(); $t->json('inspection_snapshot')->nullable();
        });
    }
    public function down(): void {
        Schema::table('store_returns',function(Blueprint $t) {
            $t->dropForeign(['store_refund_id']);
            $t->dropColumn(['operation_key','store_refund_id','received_at','completed_at','condition','inspection_snapshot']);
        });
        Schema::table('store_seller_profiles',fn(Blueprint $t)=>$t->dropColumn('policy_review_required'));
    }
};
