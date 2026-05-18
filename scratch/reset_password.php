<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::find(1);
if ($user) {
    $user->password = Hash::make('password');
    $user->save();
    echo "Password reset successfully!\n";
} else {
    echo "User not found!\n";
}
