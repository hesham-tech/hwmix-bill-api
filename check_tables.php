<?php
require 'D:/Dev/projects/hwnix-gsm/hwmix-bill-api/vendor/autoload.php';
$app = require_once 'D:/Dev/projects/hwnix-gsm/hwmix-bill-api/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== DB NAME: " . DB::connection()->getDatabaseName() . " ===\n";

$tables = Schema::getTableListing();
echo "=== SMS GATEWAY SERVER TABLES STATUS ===\n\n";

$devices = DB::table('smsgate_devices')->orderBy('updated_at', 'desc')->get();
echo "--- DEVICES (" . count($devices) . ") ---\n";
foreach ($devices as $d) {
    print_r((array)$d);
}

$lines = DB::table('smsgate_lines')->orderBy('updated_at', 'desc')->get();
echo "\n--- LINES (" . count($lines) . ") ---\n";
foreach ($lines as $l) {
    print_r((array)$l);
}

$heartbeats = DB::table('smsgate_device_heartbeats')->orderBy('id', 'desc')->limit(5)->get();
echo "\n--- RECENT HEARTBEATS (" . count($heartbeats) . ") ---\n";
foreach ($heartbeats as $h) {
    echo "[{$h->created_at}] DeviceID: {$h->device_id} | Battery: {$h->battery_level}% | Status: {$h->status}\n";
}

$messages = DB::table('smsgate_messages')->orderBy('id', 'desc')->limit(10)->get();
echo "\n--- RECENT MESSAGES (" . count($messages) . ") ---\n";
foreach ($messages as $m) {
    echo "[{$m->created_at}] Phone: {$m->phone_number} | Dir: {$m->direction} | Status: {$m->status} | Text: " . mb_substr($m->message_body ?? '', 0, 30) . "...\n";
}

$commands = DB::table('smsgate_device_commands')->orderBy('id', 'desc')->limit(5)->get();
echo "\n--- RECENT COMMANDS (" . count($commands) . ") ---\n";
foreach ($commands as $c) {
    echo "[{$c->created_at}] Command: {$c->command_type} | Status: {$c->status}\n";
}
