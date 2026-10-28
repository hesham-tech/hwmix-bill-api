<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['label_templates.manage', 'label_templates.publish', 'label_templates.set_default'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', ['label_templates.manage', 'label_templates.publish', 'label_templates.set_default'])->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
