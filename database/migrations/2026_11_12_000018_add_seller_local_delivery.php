<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
return new class extends Migration {
    public function up(): void {
        Schema::table('store_seller_profiles',function(Blueprint $t){$t->json('local_delivery')->nullable();$t->unsignedInteger('local_delivery_version')->default(1);});
        Schema::table('customer_addresses',function(Blueprint $t){$t->decimal('latitude',10,7)->nullable();$t->decimal('longitude',10,7)->nullable();$t->boolean('location_confirmed')->default(false);});
        Schema::table('warehouses',function(Blueprint $t){$t->decimal('latitude',10,7)->nullable();$t->decimal('longitude',10,7)->nullable();$t->boolean('location_confirmed')->default(false);});
        Schema::table('store_shipments',function(Blueprint $t){$t->unsignedBigInteger('shipping_account_id')->nullable()->change();$t->string('delivery_method',30)->default('carrier');$t->json('pickup_override')->nullable();});
        foreach(['settings'=>'إعداد التوصيل الذاتي','manage'=>'إدارة التوصيل الذاتي','collect'=>'توثيق تحصيل التوصيل الذاتي','pickup'=>'تعديل نقطة استلام طلب الشركة'] as $key=>$label){
            $name='store.vendor.delivery.'.$key;
            $id=DB::table('permissions')->where('name',$name)->where('guard_name','web')->value('id');
            if(!$id)$id=DB::table('permissions')->insertGetId(['name'=>$name,'guard_name'=>'web','created_at'=>now(),'updated_at'=>now()]);
            foreach(DB::table('roles')->whereIn('name',['admin.company','admin.super'])->pluck('id') as $roleId)
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id'=>$id,'role_id'=>$roleId]);
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
    public function down(): void {
        // Existing local orders require nullable carrier IDs and delivery_method: preserve historical interpretation.
        Schema::table('customer_addresses',fn(Blueprint $t)=>$t->dropColumn(['latitude','longitude','location_confirmed']));
        Schema::table('warehouses',fn(Blueprint $t)=>$t->dropColumn(['latitude','longitude','location_confirmed']));
        Schema::table('store_seller_profiles',fn(Blueprint $t)=>$t->dropColumn(['local_delivery','local_delivery_version']));
    }
};
