<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('company_setup_states',function(Blueprint $t){
            $t->id();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->string('current_step')->default('uses');$t->json('reviewed')->nullable();$t->json('deferred')->nullable();
            $t->boolean('hidden')->default(false);$t->timestamp('finished_at')->nullable();$t->timestamps();
            $t->unique(['user_id','company_id']);
        });
        Schema::table('store_seller_profiles',fn(Blueprint $t)=>$t->text('payout_destination_proposal')->nullable());
        Schema::create('setup_connection_checks',function(Blueprint $t){$t->id();$t->string('kind',20);$t->unsignedBigInteger('setting_id');$t->string('fingerprint',64);$t->timestamp('verified_at');$t->unique(['kind','setting_id']);});
        \Illuminate\Support\Facades\DB::table('permissions')->insertOrIgnore(['name'=>'guidance.setup.manage','guard_name'=>'web','created_at'=>now(),'updated_at'=>now()]);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
    public function down(): void {Schema::table('store_seller_profiles',fn(Blueprint $t)=>$t->dropColumn('payout_destination_proposal'));Schema::dropIfExists('setup_connection_checks');Schema::dropIfExists('company_setup_states');}
};
