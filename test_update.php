<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::first(); // get a super admin
\Illuminate\Support\Facades\Auth::login($user);

$plan = \App\Models\Plan::find(2);
$request = \Illuminate\Http\Request::create('/api/v1/plans/' . $plan->id, 'PUT', [
    'name' => $plan->name,
    'price_monthly' => $plan->price_monthly,
    'price_yearly' => $plan->price_yearly,
    'currency' => $plan->currency,
    'is_active' => $plan->is_active,
    'features' => array_merge($plan->features ?? [], ['custom_watermark' => true, 'another_test' => true]),
]);

$controller = $app->make(\App\Http\Controllers\PlanController::class);
$response = $controller->update(app(\App\Http\Requests\Plan\UpdatePlanRequest::class)::createFrom($request), $plan);

echo "Response Status: " . $response->getStatusCode() . "\n";
echo "Response Content: " . $response->getContent() . "\n";

$plan->refresh();
echo "Plan Features after update: " . json_encode($plan->features) . "\n";

