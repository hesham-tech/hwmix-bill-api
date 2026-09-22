<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Total companies
$total = \App\Models\Company::withoutGlobalScopes()->count();
echo "Total Companies: $total\n";

// Store enabled
$active = \App\Models\Company::withoutGlobalScopes()->storePublishEnabled()->count();
echo "Store Enabled Companies (scope): $active\n";

// First company subscriptions raw
$first = \App\Models\Company::withoutGlobalScopes()->first();
if ($first) {
    echo "First company id: {$first->id}, name: {$first->name}\n";
    $sub = $first->subscriptions()->first();
    if ($sub) {
        echo "Sub status: {$sub->status}\n";
        echo "Sub features (raw): " . $sub->getRawOriginal('features') . "\n";
        echo "Sub ends_at: " . $sub->ends_at . "\n";
    } else {
        echo "No subscriptions found for first company\n";
    }
} else {
    echo "No companies found\n";
}

// Check products with is_active_in_store
$products = \Modules\Inventory\Models\Product::withoutGlobalScopes()
    ->where('is_active_in_store', true)
    ->where('active', true)
    ->count();
echo "Products active in store (no company filter): $products\n";

$filteredProducts = \Modules\Inventory\Models\Product::withoutGlobalScopes()
    ->whereHas('company', fn($q) => $q->storePublishEnabled())
    ->where('is_active_in_store', true)
    ->where('active', true)
    ->count();
echo "Products active in store (WITH company scope): $filteredProducts\n";

// Check store_enabled distribution
echo "\n--- store_enabled distribution ---\n";
$groups = \DB::select('SELECT store_enabled, COUNT(*) as cnt FROM companies GROUP BY store_enabled');
foreach ($groups as $g) {
    echo "store_enabled={$g->store_enabled} => count={$g->cnt}\n";
}

echo "\n--- Sample companies with store_enabled=1 ---\n";
$enabled = \DB::select('SELECT id, name, store_enabled FROM companies WHERE store_enabled = 1 LIMIT 5');
foreach ($enabled as $c) {
    echo "ID:{$c->id} name:{$c->name} store_enabled:{$c->store_enabled}\n";
}

echo "\n--- Sample companies with store_enabled=0 ---\n";
$disabled = \DB::select('SELECT id, name, store_enabled FROM companies WHERE store_enabled = 0 LIMIT 5');
foreach ($disabled as $c) {
    echo "ID:{$c->id} name:{$c->name} store_enabled:{$c->store_enabled}\n";
}





