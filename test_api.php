<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$plan = \App\Models\Plan::find(2);
$features = $plan->features;
$features['custom_watermark'] = true;
$plan->update(['features' => $features]);
echo "Updated plan 2 features manually.\n";

$subs = \App\Models\CompanySubscription::where('plan_id', 2)->get();
foreach($subs as $sub) {
    $sub->update(['features' => $features]);
}
echo "Updated subs.\n";
