<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Modules\Store\Models\StoreSetting;
return new class extends Migration {
    public function up(): void {
        DB::table('permissions')->insertOrIgnore(['name'=>'store.contact.manage','guard_name'=>'web','created_at'=>now(),'updated_at'=>now()]);
        StoreSetting::firstOrCreate(['key'=>'store_public_contact'],['value'=>['email'=>'support@hwnix.com','phone'=>'01007888009','address'=>'محافظة الشرقية، مصر']]);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
    public function down(): void {
        // Preserve published contact details and delegated permission assignments on code rollback.
    }
};
