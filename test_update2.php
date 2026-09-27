<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::first();
\Illuminate\Support\Facades\Auth::login($user);

$plan = \App\Models\Plan::find(2);
$request = \Illuminate\Http\Request::create('/api/v1/plans/' . $plan->id, 'PUT', [
    'name' => $plan->name,
    'price_monthly' => $plan->price_monthly,
    'price_yearly' => $plan->price_yearly,
    'currency' => $plan->currency,
    'is_active' => $plan->is_active,
    'features' => array_merge($plan->features ?? [], ['custom_watermark' => true]),
]);

$controller = $app->make(\App\Http\Controllers\PlanController::class);

try {
    $response = $controller->update(app(\App\Http\Requests\Plan\UpdatePlanRequest::class)::createFrom($request), $plan);
    echo "Response Content: " . $response->getContent() . "\n";
} catch (\Throwable $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
