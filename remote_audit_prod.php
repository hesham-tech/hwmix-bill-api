<?php
define('LARAVEL_START', microtime(true));
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\CompanyUser;
use App\Models\Installment;

function line()
{
    echo str_repeat('-', 40) . "\n";
}

echo "=== فحص بيانات السيرفر (Production) ===\n";
line();

// 1. البحث عن كافة المستخدمين بداخلهم اسم "رضا"
echo "البحث عن مستخدمين باسم 'رضا':\n";
$users = User::where('full_name', 'like', '%رضا%')
    ->orWhere('nickname', 'like', '%رضا%')
    ->get();

if ($users->count() > 0) {
    foreach ($users as $user) {
        echo "- الاسم: " . $user->full_name . " | اللقب: " . $user->nickname . " | ID: " . $user->id . "\n";
        $cb = $user->defaultCashBox;
        echo "  رصيد الخزنة: " . ($cb ? $cb->balance : 'لا توجد') . "\n";

        $rem = Installment::where('user_id', $user->id)
            ->whereNotIn('status', ['paid', 'تم الدفع', 'canceled', 'cancelled', 'ملغي'])
            ->sum('remaining');
        echo "  الأقساط المتبقية: " . $rem . "\n";
        line();
    }
} else {
    echo "لم يتم العثور على أي مستخدم باسم 'رضا'.\n";
}

// 2. فحص المعرف 16 في CompanyUser
echo "\nفحص سجل CompanyUser (ID: 16):\n";
$cu = CompanyUser::find(16);
if ($cu) {
    echo "ID: 16 | User ID: " . $cu->user_id . " | الاسم: " . $cu->full_name_in_company . " | رصيد الجدول: " . $cu->balance_in_company . "\n";
    $linkedUser = User::find($cu->user_id);
    if ($linkedUser) {
        echo "المستخدم المرتبط: " . $linkedUser->full_name . " (ID: " . $linkedUser->id . ")\n";
        $cb = $linkedUser->defaultCashBox;
        echo "رصيد الخزنة الفعلي: " . ($cb ? $cb->balance : 'لا توجد') . "\n";
    }
} else {
    echo "المعرف 16 غير موجود في company_user.\n";
}

line();
