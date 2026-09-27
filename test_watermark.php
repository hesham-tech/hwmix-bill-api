<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::first();
\Illuminate\Support\Facades\Auth::login($user);
$request = \Illuminate\Http\Request::create('/api/v1/watermark-settings', 'GET');
$request->setUserResolver(function() use ($user) { return $user; });
$controller = $app->make(\App\Http\Controllers\CompanyController::class);
$response = $controller->getWatermarkSettings($request);
echo "Status: " . $response->getStatusCode() . "\n";
echo "Content: " . $response->getContent() . "\n";
