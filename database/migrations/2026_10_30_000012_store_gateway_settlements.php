<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('store_payment_proofs',fn(Blueprint $t)=>$t->index(['owner_type','owner_id']));
        Schema::create('store_gateway_settlements',function(Blueprint $t){
            $t->id();$t->string('operation_key',150)->unique();$t->string('fingerprint',64);$t->string('account_identity',64);
            $t->string('reference_hash',64);$t->text('reference');$t->bigInteger('gross_minor');$t->bigInteger('net_minor');
            $t->unsignedBigInteger('fee_minor');$t->unsignedBigInteger('recoverable_tax_minor')->default(0);$t->string('currency',3);
            $t->foreignId('recorded_by')->nullable()->constrained('users');$t->timestamps();$t->unique(['account_identity','reference_hash'],'store_gateway_batch_reference_unique');
        });
        Schema::create('store_gateway_settlement_items',function(Blueprint $t){
            $t->id();$t->foreignId('settlement_id')->constrained('store_gateway_settlements');
            $t->foreignId('payment_transaction_id')->constrained('payment_transactions');$t->bigInteger('gross_minor');
            $t->unique(['settlement_id','payment_transaction_id'],'store_gateway_settlement_payment_unique');
        });
    }
    public function down(): void {Schema::dropIfExists('store_gateway_settlement_items');Schema::dropIfExists('store_gateway_settlements');Schema::table('store_payment_proofs',fn(Blueprint $t)=>$t->dropIndex(['owner_type','owner_id']));}
};
