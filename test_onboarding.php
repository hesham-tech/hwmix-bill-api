<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\HwnixCash\Models\HwnixCashFinancialAccount;
use Modules\HwnixCash\Models\HwnixCashDevice;
use Modules\HwnixCash\Models\HwnixCashLine;
use Modules\HwnixCash\Models\HwnixCashMessageSource;
use Illuminate\Http\Request;
use Modules\HwnixCash\Http\Controllers\Api\v1\AgentOnboardingController;
use App\Models\User;

$user = User::first(); // Assuming a user exists
if (!$user) {
    die("No user found\n");
}
$app['auth']->setUser($user);

$request = Request::create('/api/v1/agent/onboarding/complete', 'POST', [
    'device_android_id' => 'test-device-id-123',
    'device_name' => 'Test Phone',
    'sim_phone' => '01012345678',
    'wallet_name' => 'My Test Wallet',
    'sender' => 'VodafoneCash',
    'daily_withdraw_limit' => 5000,
    'daily_deposit_limit' => 10000
]);
$request->setUserResolver(function() use ($user) { return $user; });

$controller = new AgentOnboardingController();

echo "Testing Validation Endpoint (wallet_name)...\n";
$valReq = Request::create('/api/v1/agent/validate-onboarding', 'POST', [
    'field' => 'wallet_name',
    'value' => 'My Test Wallet'
]);
$valRes = $controller->validateField($valReq);
echo "Validation Response: " . $valRes->getContent() . "\n";

echo "Testing Onboarding Completion...\n";
$response = $controller->completeOnboarding($request);
echo "Response: " . $response->getContent() . "\n";

echo "Testing Validation Endpoint again...\n";
$valRes2 = $controller->validateField($valReq);
echo "Validation Response 2: " . $valRes2->getContent() . "\n";

// Cleanup
echo "Cleaning up...\n";
HwnixCashFinancialAccount::where('name', 'My Test Wallet')->forceDelete();
HwnixCashMessageSource::where('sender_identifier', 'VodafoneCash')->forceDelete();
HwnixCashLine::where('phone_number', '01012345678')->forceDelete();
HwnixCashDevice::where('android_id', 'test-device-id-123')->forceDelete();

echo "Done.\n";
