<?php
require __DIR__."/vendor/autoload.php";
$app = require_once __DIR__."/bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::first();
Auth::login($user);

$request = new \Illuminate\Http\Request(["include_self" => 1]);
$controller = new App\Http\Controllers\UserController();
$response = $controller->index($request);

echo "Total users: " . count($response->getData()->data) . "\n";
foreach($response->getData()->data as $u) {
    echo "ID: " . $u->id . ", Name: " . $u->nickname . "\n";
}

