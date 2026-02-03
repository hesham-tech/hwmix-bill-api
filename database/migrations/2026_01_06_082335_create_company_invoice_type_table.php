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
        Schema::create('company_invoice_type', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('invoice_type_id')->constrained('invoice_types')->onDelete('cascade');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // منع التكرار: شركة واحدة + نوع واحد = سجل واحد
            $table->unique(['company_id', 'invoice_type_id'], 'company_invoice_type_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_invoice_type');
    }
};
