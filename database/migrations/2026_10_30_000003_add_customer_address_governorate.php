<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('customer_addresses',fn(Blueprint $t)=>$t->string('governorate_code',30)->nullable()); }
    public function down(): void { Schema::table('customer_addresses',fn(Blueprint $t)=>$t->dropColumn('governorate_code')); }
};
