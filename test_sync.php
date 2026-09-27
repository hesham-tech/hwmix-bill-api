<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$plan = \App\Models\Plan::first();
if (!$plan) {
    echo "No plan found\n";
    exit;
}
echo "Plan ID: " . $plan->id . "\n";
echo "Plan Features: " . json_encode($plan->features) . "\n";

$subs = \App\Models\CompanySubscription::where('plan_id', $plan->id)->whereIn('status', ['active', 'trial'])->get();
echo "Active Subs count: " . $subs->count() . "\n";
foreach ($subs as $sub) {
    echo "Sub ID {$sub->id} features: " . json_encode($sub->features) . "\n";
}

// simulate what PlanController does
$updateData = ['features' => $plan->features];
$updateData['features']['test_flag'] = true;

foreach ($subs as $sub) {
    $sub->update($updateData);
    $sub->refresh();
    echo "Sub ID {$sub->id} features after update: " . json_encode($sub->features) . "\n";
}
