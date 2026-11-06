<?php
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
return new class extends Migration {
    public function up(): void {
        foreach(['store.settings.manage','store.orders.manage','store.finance.manage','store.vendor.orders.view','store.vendor.orders.manage','store.vendor.finance.view'] as $key)
            Permission::findOrCreate($key,'web');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
    public function down(): void {
        // Preserve assigned permissions and audit history on rollback.
    }
};
