<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Upgrade Services Table
        Schema::table('services', function (Blueprint $table) {
            $table->string('period_unit')->default('month')->after('default_price'); // week, month, quarter, year
            $table->integer('period_value')->default(1)->after('period_unit');
        });

        // 2. Upgrade Subscriptions Table
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('unique_identifier')->nullable()->after('plan_id'); // Phone, IP, Account ID
            $table->decimal('balance', 15, 2)->default(0)->after('price'); // Subscription balance
            $table->string('renewal_type')->default('manual')->after('auto_renew'); // manual, automatic
        });

        // 3. Create Subscription Payments Table (Financial History for each subscription)
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->onDelete('cascade');
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Customer
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade'); // Employee
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_before', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->dateTime('payment_date');
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cash_box_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['unique_identifier', 'balance', 'renewal_type']);
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['period_unit', 'period_value']);
        });
    }
};
