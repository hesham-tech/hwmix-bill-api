<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliation_variances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('provider_account_id')->constrained('provider_accounts')->cascadeOnDelete();
            $table->decimal('expected_balance', 18, 2);
            $table->decimal('actual_balance', 18, 2);
            $table->decimal('variance_amount', 18, 2);
            $table->string('status')->index(); // Pending Investigation, Resolved
            $table->text('resolution_note')->nullable();
            $table->unsignedBigInteger('resolved_by_user_id')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('resolved_by_user_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_variances');
    }
};
