<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('store_email_verifications',function(Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $t->text('email'); $t->string('token_hash',64); $t->timestamp('expires_at');
            $t->unsignedTinyInteger('attempts')->default(0); $t->timestamp('sent_at');
            $t->timestamp('verified_at')->nullable(); $t->timestamps();
        });
        Schema::create('store_notifications',function(Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->nullable()->constrained('users');
            $t->foreignId('company_id')->nullable()->constrained('companies');
            $t->foreignId('store_order_id')->nullable()->constrained('store_orders');
            $t->string('deduplication_key',150)->unique(); $t->string('type',60); $t->string('title');
            $t->text('message'); $t->timestamp('read_at')->nullable(); $t->timestamps();
        });
        Schema::table('store_outbox',fn(Blueprint $t)=>$t->string('lock_token',36)->nullable());
    }
    public function down(): void {
        Schema::table('store_outbox',fn(Blueprint $t)=>$t->dropColumn('lock_token'));
        Schema::dropIfExists('store_notifications'); Schema::dropIfExists('store_email_verifications');
    }
};
