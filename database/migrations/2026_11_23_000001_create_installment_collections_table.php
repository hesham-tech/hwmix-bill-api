<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('installment_collections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('created_by');
            $table->uuid('request_key');
            $table->string('request_hash', 64);
            $table->json('receipt')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'request_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installment_collections');
    }
};
