<?php
// تعليق عربي: سكربت محاكاة ترقية مستخدم عادي (ليس سوبر أدمن) لباقة مدفوعة للتحقق من طلب الدفع وسلوكه.

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

use App\Models\User;
use App\Models\Plan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// لنبحث عن مستخدم عادي (ليس سوبر أدمن) يملك شركة نشطة
$user = User::withoutGlobalScopes()->where('email', '!=', 'admin@admin.com')
    ->whereNotNull('active_company_id')
    ->first();

if (!$user) {
    echo "No normal users found.\n";
    exit;
}

echo "Testing upgrade for normal user: {$user->email} (Company ID: {$user->active_company_id})\n";
Auth::login($user);

// إعداد الصلاحيات
if (function_exists('setPermissionsTeamId')) {
    setPermissionsTeamId($user->active_company_id);
}

// الباقة الاحترافية (ID 2) سعرها أكبر من 0
$plan = Plan::find(2);
if (!$plan) {
    echo "Plan ID 2 not found.\n";
    exit;
}
echo "Target Plan: {$plan->name} (Price: {$plan->price}, Trial Days: {$plan->trial_days})\n";

$request = Request::create('/api/saas/my-subscription/upgrade', 'POST', [
    'plan_id' => $plan->id,
    'months' => 1,
]);

try {
    $controller = new \App\Http\Controllers\SaaS\SaaSSubscriptionController();
    $response = $controller->upgrade($request);
    
    echo "Status Code: " . $response->getStatusCode() . "\n";
    echo "Response Content: " . json_encode(json_decode($response->getContent(), true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} catch (\Throwable $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
