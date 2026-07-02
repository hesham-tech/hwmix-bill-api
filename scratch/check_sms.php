<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$sms = Illuminate\Support\Facades\DB::table('smsgate_messages')->orderBy('id', 'desc')->limit(10)->get();
echo "Total smsgate_messages found in DB: " . $sms->count() . "\n";
foreach ($sms as $s) {
    echo "ID:{$s->id} | Phone:{$s->phone_number} | Body:{$s->message_body} | Dir:{$s->direction} | Status:{$s->status} | Ref:{$s->message_ref} | At:{$s->created_at}\n";
}
