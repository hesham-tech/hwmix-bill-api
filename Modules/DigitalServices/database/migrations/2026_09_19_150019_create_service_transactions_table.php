<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('service_definition_id')->constrained('service_definitions')->cascadeOnDelete();
            $table->foreignId('provider_account_id')->constrained('provider_accounts')->cascadeOnDelete();
            $table->unsignedBigInteger('cash_box_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('operation_type')->index();
            $table->decimal('service_amount', 18, 2)->default(0);
            $table->decimal('network_fee', 18, 2)->default(0);
            $table->decimal('shop_commission', 18, 2)->default(0);
            $table->decimal('provider_amount', 18, 2)->default(0);
            $table->decimal('cash_amount', 18, 2)->default(0);
            $table->string('status')->index(); // Pending, Awaiting External Confirmation, Completed, Failed, Reversed
            $table->string('provider_reference_id')->nullable();
            $table->unsignedBigInteger('original_transaction_id')->nullable();
            $table->string('reversal_reason')->nullable();
            $table->unsignedBigInteger('reversed_by_user_id')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['provider_account_id', 'provider_reference_id'], 'unique_provider_tx_ref'); // Idempotency
            $table->foreign('cash_box_id')->references('id')->on('cash_boxes');
            $table->foreign('original_transaction_id')->references('id')->on('service_transactions');
            $table->foreign('reversed_by_user_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_transactions');
    }
};
