<?php
define('LARAVEL_START', microtime(true));
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Installment;

echo "--- التقرير النهائي للفحص ---\n";

// العميل 10
$u10 = User::find(10);
if ($u10) {
    echo "1. رضا صقر (ID: 10):\n";
    echo "  - الاسم الكامل: " . $u10->full_name . "\n";
    echo "  - رصيد الخزنة (Cash Box): " . ($u10->defaultCashBox?->balance ?? 'لا توجد') . "\n";
    $rem10 = Installment::where('user_id', 10)->whereNotIn('status', ['paid', 'تم الدفع', 'canceled', 'cancelled', 'ملغي'])->sum('remaining');
    echo "  - مديونية الأقساط المتبقية: " . $rem10 . "\n";
}

echo "---------------------------\n";

// العميل 16
$u16 = User::find(16);
if ($u16) {
    echo "2. ايه طلعت (ID: 16):\n";
    echo "  - رصيد الخزنة (Cash Box): " . ($u16->defaultCashBox?->balance ?? 'لا توجد') . "\n";
    $rem16 = Installment::where('user_id', 16)->whereNotIn('status', ['paid', 'تم الدفع', 'canceled', 'cancelled', 'ملغي'])->sum('remaining');
    echo "  - مديونية الأقساط المتبقية: " . $rem16 . "\n";
}

echo "--- انتهى التقرير ---\n";
