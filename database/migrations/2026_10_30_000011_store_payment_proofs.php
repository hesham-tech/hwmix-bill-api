<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('store_payment_proofs',function(Blueprint $t){
            $t->id();$t->string('scope',100);$t->string('reference_hash',64);$t->string('owner_type',120);$t->unsignedBigInteger('owner_id');
            $t->unsignedBigInteger('amount_minor');$t->string('currency',3);$t->timestamp('created_at');$t->unique(['scope','reference_hash']);
        });
    }
    public function down(): void {Schema::dropIfExists('store_payment_proofs');}
};
