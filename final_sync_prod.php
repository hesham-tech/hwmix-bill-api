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

echo "بدء عملية المزامنة الشاملة على السيرفر...\n";

$users = User::all();
$updatedCount = 0;
$createdBoxes = 0;

foreach ($users as $user) {
    // شرط العميل: لا يملك أدواراً ولا صلاحيات
    if ($user->roles()->count() > 0 || $user->permissions()->count() > 0) {
        continue;
    }

    // جلب الشركات المرتبطة
    $companies = DB::table('company_user')
        ->where('user_id', $user->id)
        ->pluck('company_id');

    foreach ($companies as $companyId) {
        // جلب أو إنشاء الخزنة
        $cashBox = DB::table('cash_boxes')
            ->where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->where('is_default', true)
            ->first();

        if (!$cashBox) {
            echo "إضافة خزنة للعميل: {$user->full_name} (ID: {$user->id})\n";
            $boxInstance = $cashBoxService->createDefaultCashBoxForUserCompany($user->id, $companyId, 1);
            if ($boxInstance) {
                $cashBox = (object) $boxInstance->toArray();
                $createdBoxes++;
            } else {
                continue;
            }
        }

        // حساب الميزانية
        $totalRemaining = DB::table('installments')
            ->where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->whereNotIn('status', ['paid', 'تم الدفع', 'canceled', 'cancelled', 'ملغي'])
            ->sum('remaining');

        $newBalance = -abs($totalRemaining);

        if (round($cashBox->balance, 2) != round($newBalance, 2)) {
            echo "تحديث رصيد: {$user->full_name} | من {$cashBox->balance} إلى {$newBalance}\n";
            DB::table('cash_boxes')
                ->where('id', $cashBox->id)
                ->update(['balance' => $newBalance]);
            $updatedCount++;
        }
    }
}

echo "\nتمت العملية بنجاح:\n";
echo "- الخزائن المنشأة: $createdBoxes\n";
echo "- الأرصدة المحدثة: $updatedCount\n";
