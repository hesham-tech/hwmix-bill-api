<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('store_seller_profiles',fn(Blueprint $table)=>$table->json('tax_profile')->nullable());
        Schema::table('store_seller_profiles',fn(Blueprint $table)=>$table->json('business_profile')->nullable());
        Schema::table('store_order_items',fn(Blueprint $table)=>$table->json('tax_snapshot')->nullable());
    }
    public function down(): void {
        Schema::table('store_order_items',fn(Blueprint $table)=>$table->dropColumn('tax_snapshot'));
        Schema::table('store_seller_profiles',fn(Blueprint $table)=>$table->dropColumn('tax_profile'));
        Schema::table('store_seller_profiles',fn(Blueprint $table)=>$table->dropColumn('business_profile'));
    }
};
