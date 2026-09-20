<?php
// جدول عناوين الشحن الخاصة بالعملاء في المتجر الإلكتروني
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('label', 50)->default('home');
            $table->string('recipient_name');
            $table->string('phone', 20);
            $table->string('country', 100)->default('مصر');
            $table->string('city', 100);
            $table->string('district', 100)->nullable();
            $table->string('street')->nullable();
            $table->string('building')->nullable();
            $table->string('floor')->nullable();
            $table->string('apartment')->nullable();
            $table->text('landmark')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->index('user_id');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
    }
};
