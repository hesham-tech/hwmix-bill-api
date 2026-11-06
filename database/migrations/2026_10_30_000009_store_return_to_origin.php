<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('store_return_to_origins',function(Blueprint $t){
            $t->id();$t->foreignId('store_sub_order_id')->unique()->constrained('store_sub_orders');
            $t->string('fingerprint',64);$t->string('condition',20);$t->string('reason',60);$t->string('carrier_reference',150);
            $t->unsignedBigInteger('carrier_cost_minor');$t->string('cost_payer',20);$t->timestamp('received_at');
            $t->foreignId('received_by')->nullable()->constrained('users');$t->unsignedBigInteger('erp_operation_id')->nullable();$t->timestamps();
        });
    }
    public function down(): void {Schema::dropIfExists('store_return_to_origins');}
};
