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
        Schema::create('cash_box_user', function (Blueprint $table) {
            $table->foreignId('cash_box_id')->constrained('cash_boxes')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->primary(['cash_box_id', 'user_id'], 'cash_box_user_primary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_box_user');
    }
};
