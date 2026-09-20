<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('service_definition_id')->constrained('service_definitions')->cascadeOnDelete();
            $table->string('network_fee_type')->default('fixed');
            $table->decimal('network_fee_value', 18, 2)->default(0);
            $table->string('shop_commission_type')->default('tier_500'); // fixed, percentage, tier_500
            $table->decimal('shop_commission_value', 18, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_rules');
    }
};
