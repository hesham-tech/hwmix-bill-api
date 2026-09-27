<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

foreach(\App\Models\Plan::all() as $p) {
    echo "Plan {$p->id} - {$p->name}: " . json_encode($p->features) . "\n";
}
