<?php

// Script to test upgrade logic and see the exact exception.
require __DIR__ . '/../vendor/autoload.classmap.php'; // wait, standard laravel bootstrap is better:
require __DIR__ . '/../bootstrap/app.php';

use App\Models\User;
use App\Models\Plan;
use App\Models\Company;
use App\Http\Controllers\SaaS\SaaSSubscriptionController;
use Illuminate\Http\Request;

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

// Instead of kernel handle, let's bootstrap and call it directly:
$app->make('db');
// Let's create dummy records and call SaaSSubscriptionController upgrade
try {
    $plan = Plan::create([
        'name' => 'Test plan',
        'code' => 'test_code_' . time(),
        'price' => 250.00,
        'currency' => 'EGP',
        'duration' => 1,
        'duration_unit' => 'months',
        'is_active' => true,
    ]);

    $user = User::first();
    Auth::login($user);

    $controller = new SaaSSubscriptionController();
    $request = new Request();
    $request->merge(['plan_id' => $plan->id]);

    echo "Calling upgrade...\n";
    $res = $controller->upgrade($request);
    echo "Response status: " . $res->getStatusCode() . "\n";
    echo "Response body: " . $res->getContent() . "\n";
} catch (\Throwable $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    echo "Stack trace: \n" . $e->getTraceAsString() . "\n";
}
