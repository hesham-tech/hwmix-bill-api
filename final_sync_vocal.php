<?php
define('LARAVEL_START', microtime(true));
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Installment;
use App\Services\CashBoxService;
use Illuminate\Support\Facades\DB;

$cashBoxService = app(CashBoxService::class);

echo "بدء عملية المزامنة الشاملة (Vocal Mode)...\n";

$users = User::all();
echo "عدد المستخدمين الإجمالي: " . $users->count() . "\n";

$updatedCount = 0;
$createdBoxes = 0;
$skippedCount = 0;

foreach ($users as $user) {
    echo "فحص المستخدم: [ID: {$user->id}] {$user->full_name}\n";

    // شرط العميل: لا يملك أدواراً ولا صلاحيات
    if ($user->roles()->count() > 0 || $user->permissions()->count() > 0) {
        echo " - تخطي: موظف (لديه صلاحيات/أدوار)\n";
        $skippedCount++;
        continue;
    }

    echo " - الحالة: عميل نقي (Customer)\n";

    // جلب الشركات المرتبطة
    $companies = DB::table('company_user')
        ->where('user_id', $user->id)
        ->pluck('company_id');

    if ($companies->isEmpty()) {
        echo " - تخطي: غير مرتبط بأي شركة في جدول company_user\n";
        continue;
    }

    foreach ($companies as $companyId) {
        // جلب أو إنشاء الخزنة
        $cashBox = DB::table('cash_boxes')
            ->where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->where('is_default', true)
            ->first();

        if (!$cashBox) {
            echo " - تنبيه: لا توجد خزنة افتراضية في الشركة {$companyId}. جاري المحاولة لإنشائها...\n";
            $boxInstance = $cashBoxService->createDefaultCashBoxForUserCompany($user->id, $companyId, 1);
            if ($boxInstance) {
                $cashBox = (object) $boxInstance->toArray();
                echo "   + تم إنشاء خزنة جديدة بنجاح (ID: {$cashBox->id})\n";
                $createdBoxes++;
            } else {
                echo "   ! فشل إنشاء الخزنة.\n";
                continue;
            }
        } else {
            echo " - خزنة العميل موجودة (ID: {$cashBox->id}) | الرصيد الحالي: {$cashBox->balance}\n";
        }

        // حساب الميزانية
        $totalRemaining = DB::table('installments')
            ->where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->whereNotIn('status', ['paid', 'تم الدفع', 'canceled', 'cancelled', 'ملغي'])
            ->sum('remaining');

        echo " - إجمالي الأقساط المتبقية: {$totalRemaining}\n";
        $newBalance = -abs($totalRemaining);

        if (round($cashBox->balance, 2) != round($newBalance, 2)) {
            echo "   >>> تحديث الرصيد من {$cashBox->balance} إلى {$newBalance}\n";
            DB::table('cash_boxes')
                ->where('id', $cashBox->id)
                ->update(['balance' => $newBalance]);
            $updatedCount++;
        } else {
            echo "   ... الرصيد مطابق للأقساط المتبقية.\n";
        }
    }
    echo "---------------------------\n";
}

echo "\nتمت العملية بنجاح:\n";
echo "- الموظفون الذين تم تخطيهم: $skippedCount\n";
echo "- الخزائن المنشأة: $createdBoxes\n";
echo "- الأرصدة المحدثة: $updatedCount\n";
