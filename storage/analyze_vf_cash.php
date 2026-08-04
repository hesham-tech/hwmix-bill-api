<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$messages = DB::table('hwnix_cash_messages')
    ->where(function ($q) {
        $q->where('phone_number', 'like', '%VF-Cash%')
          ->orWhere('phone_number', 'like', '%Vodafone%')
          ->orWhere('message_body', 'like', '%فودافون كاش%');
    })
    ->orderBy('id', 'asc')
    ->get();

echo "TOTAL_MESSAGES_COUNT:" . $messages->count() . "\n\n";

$analyzed = [];
foreach ($messages as $m) {
    $body = trim($m->message_body);
    $sender = $m->phone_number;
    $id = $m->id;
    $created = $m->created_at;
    $status = $m->status;

    // Pattern matching to classify
    $type = 'أخرى / إعلانات';
    if (preg_match('/رصيد حسابك.*فودافون كاش/ui', $body) || preg_match('/رصيد حسابك الحالي/ui', $body) || preg_match('/رصيد محك/ui', $body)) {
        $type = 'استعلام عن الرصيد (Balance Inquiry)';
    } elseif (preg_match('/تم تحويل.*جنيه.*لـ/ui', $body) || preg_match('/تم تحويل.*جنيه.*لي/ui', $body) || preg_match('/تم تحويل.*إلى/ui', $body)) {
        $type = 'تحويل أموال صادرة (Send Money)';
    } elseif (preg_match('/تم استقبال.*جنيه.*من/ui', $body) || preg_match('/تم تحويل.*جنيه.*من/ui', $body) || preg_match('/استلمت/ui', $body)) {
        $type = 'استقبال أموال واردة (Receive Money)';
    } elseif (preg_match('/سحب/ui', $body) || preg_match('/تم خصم/ui', $body) || preg_match('/تم إيداع/ui', $body)) {
        $type = 'إيداع / سحب كاش (Cash In / Out)';
    } elseif (preg_match('/تم شحن/ui', $body) || preg_match('/شحن كارت/ui', $body) || preg_match('/دفع فواتير/ui', $body) || preg_match('/تجديد باقة/ui', $body)) {
        $type = 'شحن / خدمات ودفع فواتير (Recharge & Services)';
    } elseif (preg_match('/كارت/ui', $body) || preg_match('/رمز/ui', $body) || preg_match('/الرقم السري/ui', $body)) {
        $type = 'كروت شفرة / كروت أونلاين / رموز أمان (Online Card / OTP)';
    }

    if (!isset($analyzed[$type])) {
        $analyzed[$type] = [];
    }

    $analyzed[$type][] = [
        'id' => $id,
        'sender' => $sender,
        'created_at' => $created,
        'status' => $status,
        'body' => $body
    ];
}

foreach ($analyzed as $category => $items) {
    echo "=========================================\n";
    echo "CATEGORY: {$category} (عدد الرسائل: " . count($items) . ")\n";
    echo "=========================================\n";
    foreach ($items as $idx => $item) {
        echo "[$idx] ID: {$item['id']} | Sender: {$item['sender']} | Date: {$item['created_at']} | Status: {$item['status']}\n";
        echo "TEXT: {$item['body']}\n\n";
    }
}
