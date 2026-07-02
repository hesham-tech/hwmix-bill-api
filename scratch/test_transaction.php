<?php

use App\Models\User;
use App\Models\Transaction;
use App\Models\Company;
use App\Models\CashBox;
use Illuminate\Support\Facades\Auth;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// جلب المستخدم رقم 5
$user = User::withoutGlobalScopes()->find(5);

if (!$user) {
    echo "المستخدم رقم 5 غير موجود في قاعدة البيانات!\n";
    exit;
}

$companyId = $user->company_id ?? 2;
$company = Company::find($companyId);

echo "المستخدم المختار: " . $user->name . " (ID: " . $user->id . ")\n";
echo "الشركة النشطة له: " . ($company ? $company->name : 'NULL') . " (ID: " . $companyId . ")\n";

// تعيين الشركة النشطة والفرع النشط في الإعدادات لتخطي فلاتر النطاق
$user->active_company_id = $companyId;
$user->branch_id = 2; // الفرع 2
$user->save();

config(['app.active_branch_id' => 2]);

Auth::login($user);

// جلب الخزنة بدون قيود للتأكد من وجودها
$cashBox = CashBox::withoutGlobalScopes()->where('user_id', $user->id)->where('company_id', $companyId)->first();

if (!$cashBox) {
    echo "لا توجد خزنة للمستخدم رقم 5!\n";
    exit;
}

echo "الخزنة المستخدمة: " . $cashBox->name . " (ID: " . $cashBox->id . ") رصيدها الحالي: " . $cashBox->balance . "\n";

try {
    echo "محاولة إجراء إيداع بقيمة 50 للمستخدم رقم 5...\n";
    // نمرر الخزنة
    $result = $user->deposit(50, $cashBox->id, "تجربة إيداع للمستخدم 5 بعد تحديد الفرع");
    echo "نتيجة دالة الإيداع: " . ($result ? "نجاح" : "فشل") . "\n";
    
    // تفقد المعاملات المضافة للمسخدم رقم 5
    $count = Transaction::withoutGlobalScopes()->where('user_id', $user->id)->count();
    echo "عدد المعاملات المسجلة للمستخدم 5 في جدول transactions: " . $count . "\n";
    if ($count > 0) {
        $latest = Transaction::withoutGlobalScopes()->where('user_id', $user->id)->latest()->first();
        echo "تفاصيل آخر معاملة للمستخدم 5:\n";
        print_r($latest->toArray());
    }
} catch (\Throwable $e) {
    echo "حدث خطأ أثناء الإيداع:\n";
    echo $e->getMessage() . "\n";
    echo "في السطر: " . $e->getLine() . " بملف: " . $e->getFile() . "\n";
}
