<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // تحديث enum لحالة الفاتورة
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE invoices MODIFY COLUMN status ENUM('draft', 'confirmed', 'paid', 'partially_paid', 'overdue', 'canceled', 'refunded') DEFAULT 'draft'");
        }

        // إضافة حقل payment_status منفصل
        Schema::table('invoices', function (Blueprint $table) {
            $table->enum('payment_status', [
                'unpaid',
                'partially_paid',
                'paid',
                'overpaid'
            ])->default('unpaid')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('payment_status');
        });

        // إرجاع enum للقيم القديمة
        // إرجاع enum للقيم القديمة
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE invoices MODIFY COLUMN status ENUM('draft', 'confirmed', 'canceled') DEFAULT 'confirmed'");
        }
    }
};
