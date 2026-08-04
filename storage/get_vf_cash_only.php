<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$messages = DB::table('hwnix_cash_messages')
    ->where('phone_number', 'VF-Cash')
    ->orderBy('id', 'asc')
    ->get();

echo "TOTAL VF-CASH MESSAGES: " . $messages->count() . "\n\n";

foreach ($messages as $m) {
    echo "ID: {$m->id} | Date: {$m->created_at} | Status: {$m->status}\n";
    echo "TEXT: {$m->message_body}\n";
    echo "---------------------------------------------------------\n";
}
