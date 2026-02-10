<?php
define('LARAVEL_START', microtime(true));
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$users = User::all();
echo "Total Users in DB: " . $users->count() . "\n";

foreach ($users as $user) {
    echo "Processing User: " . $user->id . " | Name: " . $user->full_name . "\n";
    echo " - Roles Count: " . $user->roles()->count() . "\n";
    echo " - Permissions Count: " . $user->permissions()->count() . "\n";

    if ($user->roles()->count() == 0 && $user->permissions()->count() == 0) {
        echo "   >>> This is a CUSTOMER\n";
    } else {
        echo "   >>> This is an EMPLOYEE\n";
    }
}
