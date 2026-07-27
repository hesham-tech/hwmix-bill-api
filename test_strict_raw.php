<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$result = Modules\AiPlatform\Facades\AI::capability('text.generate')
    ->prompt('product.description.generate')
    ->with([
        'product_name' => 'Samsung Galaxy S24 Ultra - 512GB',
        'features' => 'موبايلات'
    ])
    ->forCompany(1)
    ->run();

echo "=== النتيجة النقية النهائية للوصف الخام ===\n\n";
echo "SUCCESS: " . ($result->successful ? 'YES' : 'NO') . "\n";
echo "TRACE_ID: " . $result->traceId . "\n\n";
echo "--- BEGIN RESULT ---\n";
echo $result->content . "\n";
echo "--- END RESULT ---\n";
