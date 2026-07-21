<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== TIMEZONE INFO ===\n";
echo "Laravel config app.timezone: " . config('app.timezone') . "\n";
echo "Laravel now(): " . now()->format('Y-m-d H:i:s P') . "\n";

echo "\n=== DEVICE INFO ===\n";
$device = DB::table('smsgate_devices')->where('id', 1)->first();
if ($device) {
    echo "ID: {$device->id} | Name: {$device->device_name} | UUID: {$device->uuid} | Android ID: {$device->android_id} | Active: {$device->status} | Last Seen: {$device->last_seen_at}\n";
} else {
    echo "Device 1 not found.\n";
}

echo "\n=== SIM LINES ===\n";
if ($device) {
    $lines = DB::table('smsgate_lines')->where('device_android_id', $device->android_id)->get();
    foreach ($lines as $line) {
        echo "ID: {$line->id} | Slot: {$line->slot_index} | Phone: {$line->phone_number} | Active: {$line->status} | Updated: {$line->updated_at}\n";
    }
}

echo "\n=== RECENT SMS DEVICE COMMANDS ===\n";
$commands = DB::table('smsgate_device_commands')->orderBy('id', 'desc')->limit(5)->get();
foreach ($commands as $cmd) {
    echo "ID: {$cmd->id} | Device: {$cmd->sms_device_id} | Type: {$cmd->command_type} | Status: {$cmd->status} | Created: {$cmd->created_at} | Updated: {$cmd->updated_at}\n";
    echo "Payload: " . $cmd->payload . "\n---\n";
}

echo "\n=== RECENT SMS MESSAGES ===\n";
$messages = DB::table('smsgate_messages')->orderBy('id', 'desc')->limit(5)->get();
foreach ($messages as $msg) {
    echo "ID: {$msg->id} | Phone: {$msg->phone_number} | Dir: {$msg->direction} | Status: {$msg->status} | Device: {$msg->sms_device_id} | Created: {$msg->created_at} | Updated: {$msg->updated_at}\n---\n";
}
