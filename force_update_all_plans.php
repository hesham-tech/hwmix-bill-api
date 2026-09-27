<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

foreach(\App\Models\Plan::all() as $plan) {
    $features = $plan->features ?? [];
    if (!isset($features['custom_watermark']) || !$features['custom_watermark']) {
        $features['custom_watermark'] = true;
        $plan->update(['features' => $features]);
        echo "Updated Plan {$plan->id}\n";
    }
    
    $subs = \App\Models\CompanySubscription::where('plan_id', $plan->id)->get();
    foreach($subs as $sub) {
        $subFeatures = $sub->features ?? [];
        if (!isset($subFeatures['custom_watermark']) || !$subFeatures['custom_watermark']) {
            $subFeatures['custom_watermark'] = true;
            $sub->update(['features' => $subFeatures]);
            echo "Updated Sub {$sub->id} for Plan {$plan->id}\n";
        }
    }
}
echo "Done.\n";
