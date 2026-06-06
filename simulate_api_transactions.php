<?php

use App\Models\User;
use Modules\Accounting\Http\Controllers\TransactionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// جلب المستخدم رقم 5 (ابو ندي)
$user = User::withoutGlobalScopes()->find(5);
if (!$user) {
    echo "المستخدم رقم 5 غير موجود!\n";
    exit;
}

$user->active_company_id = 2;
$user->save();

Auth::login($user);

// محاكاة طلب GET لـ /transactions
$request = Request::create('/api/v1/transactions', 'GET', [
    'per_page' => 10
]);

echo "المستخدم المستعلم: " . $user->name . " (ID: " . $user->id . ")\n";
echo "الشركة النشطة: " . $user->active_company_id . "\n";
echo "هل يمتلك صلاحية admin.company؟ " . ($user->hasPermissionTo('admin.company') ? 'نعم' : 'لا') . "\n";
echo "هل يمتلك صلاحية transactions.view_all؟ " . ($user->hasPermissionTo('transactions.view_all') ? 'نعم' : 'لا') . "\n";

try {
    $controller = new TransactionController();
    $response = $controller->transactions($request);
    
    echo "\nحالة الرد من الـ API: " . $response->getStatusCode() . "\n";
    $data = json_decode($response->getContent(), true);
    
    if (isset($data['success']) && $data['success']) {
        echo "نجح جلب المعاملات!\n";
        $pagination = $data['data'];
        echo "العدد الكلي للمعاملات المرجعة: " . ($pagination['total'] ?? 0) . "\n";
        if (!empty($pagination['data'])) {
            echo "تفاصيل أول معاملة مرجعة:\n";
            print_r($pagination['data'][0]);
        } else {
            echo "قائمة المعاملات المرجعة فارغة تماماً!\n";
        }
    } else {
        echo "فشل جلب المعاملات! تفاصيل الرد:\n";
        print_r($data);
    }
} catch (\Throwable $e) {
    echo "حدث خطأ أثناء محاكاة الـ API:\n" . $e->getMessage() . "\n";
    echo "في السطر: " . $e->getLine() . " بملف: " . $e->getFile() . "\n";
}
