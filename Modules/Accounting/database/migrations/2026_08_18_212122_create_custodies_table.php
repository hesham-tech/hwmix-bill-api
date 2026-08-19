<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('custodies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('cashbox_id');
            $table->decimal('amount', 18, 2);
            $table->decimal('settled_cash_amount', 18, 2)->default(0);
            $table->decimal('settled_expenses_amount', 18, 2)->default(0);
            $table->string('status', 20)->default('open'); // open, closed, canceled
            $table->date('issue_date');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custodies');
    }
};
