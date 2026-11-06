<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('store_shipping_rates',function(Blueprint $t) {
            $t->unsignedBigInteger('carrier_fee_minor')->nullable();
            $t->string('subsidy_payer',20)->default('seller');
        });
        Schema::table('store_shipments',fn(Blueprint $t)=>$t->text('remittance_snapshot')->nullable());
        \Spatie\Permission\Models\Permission::findOrCreate('store.vendor.finance.manage','web');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
    public function down(): void {
        Schema::table('store_shipments',fn(Blueprint $t)=>$t->dropColumn('remittance_snapshot'));
        Schema::table('store_shipping_rates',fn(Blueprint $t)=>$t->dropColumn(['carrier_fee_minor','subsidy_payer']));
    }
};
