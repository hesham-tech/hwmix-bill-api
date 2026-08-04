<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

// Query all messages across all companies, lines, devices without scope
$allMessages = DB::table('hwnix_cash_messages')
    ->where(function ($q) {
        $q->where('phone_number', 'like', '%VF%')
          ->orWhere('phone_number', 'like', '%Vodafone%')
          ->orWhere('message_body', 'like', '%فودافون%')
          ->orWhere('message_body', 'like', '%رصيد%')
          ->orWhere('message_body', 'like', '%تم تحويل%')
          ->orWhere('message_body', 'like', '%تم استلام%');
    })
    ->get();

echo "=========================================\n";
echo "TOTAL SYSTEM MESSAGES MATCHED: " . $allMessages->count() . "\n";
echo "=========================================\n\n";

// Count senders
$senders = [];
foreach ($allMessages as $m) {
    $s = $m->phone_number;
    $senders[$s] = ($senders[$s] ?? 0) + 1;
}
echo "--- SENDERS SUMMARY ---\n";
foreach ($senders as $s => $cnt) {
    echo "Sender: '{$s}' => Count: {$cnt}\n";
}
echo "\n";

// Let's normalize message bodies to group unique text templates/patterns
$patterns = [];
foreach ($allMessages as $m) {
    $body = trim($m->message_body);
    
    // Replace numbers, dates, IDs, phones with placeholders to extract structural pattern
    $normalized = preg_replace('/\d+(\.\d+)?/', '{NUM}', $body);
    $normalized = preg_replace('/(01[0125]\d{8})/', '{PHONE}', $normalized);

    if (!isset($patterns[$normalized])) {
        $patterns[$normalized] = [
            'count' => 0,
            'sample_raw' => $body,
            'sender' => $m->phone_number,
            'sample_id' => $m->id,
            'sample_date' => $m->created_at,
            'status' => $m->status
        ];
    }
    $patterns[$normalized]['count']++;
}

echo "=========================================\n";
echo "DISTINCT MESSAGE STRUCTURAL PATTERNS: " . count($patterns) . "\n";
echo "=========================================\n\n";

$i = 1;
foreach ($patterns as $norm => $info) {
    echo "--- PATTERN #{$i} (Occurrences: {$info['count']}) ---\n";
    echo "Sender: {$info['sender']} | Sample ID: {$info['sample_id']} | Status: {$info['status']} | Date: {$info['sample_date']}\n";
    echo "RAW SAMPLE:\n\"{$info['sample_raw']}\"\n\n";
    $i++;
}
