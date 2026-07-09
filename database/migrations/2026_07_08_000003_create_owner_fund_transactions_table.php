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
        Schema::create('owner_fund_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->onDelete('set null');
            $table->foreignId('cashbox_id')->constrained('cash_boxes')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('type', [
                'capital_increase',
                'partner_contribution',
                'loan_from_owner',
                'loan_to_owner',
                'advance_from_owner',
                'advance_to_partner',
                'drawings',
                'profit_distribution'
            ])->index();
            $table->decimal('amount', 18, 2);
            $table->dateTime('entry_date')->index();
            $table->string('description')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('owner_fund_transactions');
    }
};
