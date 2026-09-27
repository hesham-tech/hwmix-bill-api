<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$companies = \App\Models\Company::where('id', '>', 1)->get();
if ($companies->isEmpty()) {
    echo "No companies other than 1 found\n";
    exit;
}

foreach($companies as $company) {
    echo "====================================\n";
    echo "Company ID: {$company->id} - {$company->name}\n";
    $sub = $company->activeSubscription;
    if (!$sub) {
        echo "No active subscription found for company {$company->id}\n";
        
        // Let's see what subscriptions it DOES have
        $allSubs = \App\Models\CompanySubscription::where('company_id', $company->id)->get();
        foreach($allSubs as $s) {
            echo "  Sub {$s->id} status: {$s->status}, starts_at: {$s->starts_at}, ends_at: {$s->ends_at}\n";
        }
        continue;
    }

    echo "Sub ID: {$sub->id}\n";
    echo "Sub Status: {$sub->status}\n";
    echo "Sub Plan ID: {$sub->plan_id}\n";
    echo "Sub Plan Name: {$sub->plan->name}\n";
    echo "Sub Features JSON: " . json_encode($sub->features) . "\n";
    echo "Can Customize Watermark: " . ($company->canCustomizeWatermark() ? 'Yes' : 'No') . "\n";
}
