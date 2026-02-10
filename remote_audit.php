<?php
// Script to be run on server to verify user data
define('LARAVEL_START', microtime(true));
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\CompanyUser;
use App\Models\Installment;
use Illuminate\Support\Facades\DB;

function line()
{
    echo str_repeat('-', 40) . "\n";
}

echo "=== فحص بيانات السيرفر ===\n";
line();

// 1. فحص رضا نجاح صقر
$user = User::where('full_name', 'like', '%رضا نجاح صقر%')->first();
if ($user) {
    echo "العميل: " . $user->full_name . " (ID: " . $user->id . ")\n";
    $cb = $user->defaultCashBox;
    echo "رصيد الخزنة (Cash Box): " . ($cb ? $cb->balance : 'لا توجد خزنة') . "\n";

    $remaining = Installment::where('user_id', $user->id)
        ->whereNotIn('status', ['paid', 'تم الدفع', 'canceled', 'cancelled', 'ملغي'])
        ->sum('remaining');
    echo "إجمالي الأقساط المتبقية: " . $remaining . "\n";
} else {
    echo "خطأ: لم يتم العثور على 'رضا نجاح صقر' في جدول المستخدمين.\n";
}

line();

// 2. فحص المعرف 16 في CompanyUser
$cu = CompanyUser::find(16);
if ($cu) {
    echo "سجل CompanyUser رقم 16:\n";
    echo "مرتبط بالمستخدم رقم (User ID): " . $cu->user_id . "\n";
    echo "الاسم في الشركة: " . $cu->full_name_in_company . "\n";
    echo "الرصيد المسجل في جدول الشركة: " . $cu->balance_in_company . "\n";
    echo "الدور: " . $cu->role . "\n";

    // فحص المستخدم المرتبط به (إذا لم يكن هو رضا صقر)
    if (!$user || $user->id != $cu->user_id) {
        $linkedUser = User::find($cu->user_id);
        echo "تنبيه: المعرف 16 مرتبط بمستخدم آخر هو: " . ($linkedUser ? $linkedUser->full_name : 'غير موجود') . "\n";
    }
} else {
    echo "خطأ: المعرف 16 غير موجود في جدول company_user على السيرفر.\n";
}

line();
echo "تم الانتهاء من الفحص.\n";
