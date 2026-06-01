<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    echo "=== USERS ===\n";
    $users = \App\Models\User::all();
    foreach ($users as $u) {
        $roles = $u->getRoleNames()->toArray();
        $permissions = $u->getPermissionNames()->toArray();
        echo "ID: {$u->id} | Name: {$u->name} | Username: {$u->username} | Email: {$u->email} | Active Company ID: {$u->active_company_id}\n";
        echo "  Roles: " . implode(', ', $roles) . "\n";
        echo "  Permissions: " . implode(', ', $permissions) . "\n";
        
        $companyUsers = \App\Models\CompanyUser::where('user_id', $u->id)->get();
        echo "  Company Memberships (" . $companyUsers->count() . "):\n";
        foreach ($companyUsers as $cu) {
            echo "    Company ID: {$cu->company_id} | Type: {$cu->customer_type_in_company} | Status: {$cu->status} | Nickname: {$cu->nickname_in_company}\n";
        }
        echo "----------------------------------------\n";
    }
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
