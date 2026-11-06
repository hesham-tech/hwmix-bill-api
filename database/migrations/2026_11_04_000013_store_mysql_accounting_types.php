<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        // Automated marketplace documents have no human actor; never attribute them to the buyer.
        foreach(['invoices','invoice_items'] as $table) {
            Schema::table($table,fn(Blueprint $t)=>$t->unsignedBigInteger('created_by')->nullable()->change());
        }
        Schema::table('store_return_to_origins',fn(Blueprint $t)=>$t->uuid('erp_operation_id')->nullable()->change());
    }
    public function down(): void {
        // Retain the backward-compatible widening: narrowing would destroy UUIDs or reject system documents.
    }
};
