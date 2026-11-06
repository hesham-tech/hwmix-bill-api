<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if(!Schema::hasIndex('invoices','invoice_reminder_scan_index'))
            Schema::table('invoices',fn(Blueprint $t)=>$t->index(['company_id','payment_status','due_date','id'],'invoice_reminder_scan_index'));
    }
    public function down(): void {
        Schema::table('invoices',fn(Blueprint $t)=>$t->dropIndex('invoice_reminder_scan_index'));
    }
};
