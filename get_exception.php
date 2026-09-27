<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::first();
\Illuminate\Support\Facades\Auth::login($user);

$plan = \App\Models\Plan::find(2);

try {
    \Illuminate\Support\Facades\DB::beginTransaction();
    $validatedData = [
        'name' => $plan->name,
        'features' => array_merge($plan->features ?? [], ['custom_watermark' => true])
    ];
    $plan->update($validatedData);

    $updateData = [];
    if (isset($validatedData['features'])) $updateData['features'] = $validatedData['features'];
    
    if (!empty($updateData)) {
        $activeSubs = \App\Models\CompanySubscription::where('plan_id', $plan->id)
            ->whereIn('status', ['active', 'trial'])
            ->get();
        foreach($activeSubs as $sub) {
            $sub->update($updateData);
        }
    }
    \Illuminate\Support\Facades\DB::rollBack();
    echo "No exception in raw logic.\n";
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
