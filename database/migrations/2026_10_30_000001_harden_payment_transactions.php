<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('payment_transactions', function (Blueprint $t) {
            $t->string('idempotency_key',64)->nullable()->unique();
            $t->string('gateway_driver',40)->nullable();
            $t->text('gateway_config_snapshot')->nullable();
            $t->boolean('gateway_test_mode')->default(false);
            $t->string('gateway_payment_reference')->nullable()->index();
            $t->timestamp('verified_at')->nullable();
            $t->unsignedBigInteger('refunded_minor')->default(0);
        });
        Schema::create('payment_webhook_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payment_transaction_id')->constrained('payment_transactions');
            $t->string('event_key',64)->unique();
            $t->string('status',30);
            $t->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('payment_webhook_events');
        Schema::table('payment_transactions', function (Blueprint $t) {
            $t->dropColumn(['idempotency_key','gateway_driver','gateway_config_snapshot','gateway_test_mode','gateway_payment_reference','verified_at','refunded_minor']);
        });
    }
};
