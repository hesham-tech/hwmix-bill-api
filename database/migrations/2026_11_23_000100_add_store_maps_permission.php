<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
 public function up(): void {DB::table('permissions')->insertOrIgnore(['name'=>'store.maps.manage','guard_name'=>'web','created_at'=>now(),'updated_at'=>now()]);app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();}
 public function down(): void {} // Preserve encrypted settings and explicit delegations on rollback.
};
