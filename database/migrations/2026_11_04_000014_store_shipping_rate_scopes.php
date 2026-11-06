<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if(!Schema::hasColumn('store_shipping_rates','scope_company_id'))
            Schema::table('store_shipping_rates',fn(Blueprint $t)=>$t->unsignedBigInteger('scope_company_id')->default(0));
        // Install the replacement first: MySQL uses the old leading index for the account FK.
        if(!Schema::hasIndex('store_shipping_rates','store_shipping_rate_scope_unique'))
            Schema::table('store_shipping_rates',fn(Blueprint $t)=>$t->unique(['shipping_account_id','governorate_code','scope_company_id'],'store_shipping_rate_scope_unique'));
        if(Schema::hasIndex('store_shipping_rates','store_shipping_rates_shipping_account_id_governorate_code_unique'))
            Schema::table('store_shipping_rates',fn(Blueprint $t)=>$t->dropUnique(['shipping_account_id','governorate_code']));
    }
    public function down(): void {
        // Keep scoped rates: collapsing them would discard approved seller exceptions.
    }
};
