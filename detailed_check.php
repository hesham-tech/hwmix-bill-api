<?php
define('LARAVEL_START', microtime(true));
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\CashBox;
use App\Models\Installment;
use Illuminate\Support\Facades\DB;

function checkUser($id)
{
    $user = User::find($id);
    if (!$user) {
        echo "User $id not found.\n";
        return;
    }
    echo "Processing User: $id | Name: {$user->full_name}\n";

    $boxes = CashBox::where('user_id', $id)->get();
    echo " - Cash Boxes Count: " . $boxes->count() . "\n";
    foreach ($boxes as $box) {
        echo "   * ID: {$box->id} | Company: {$box->company_id} | Balance: {$box->balance} | Default: " . ($box->is_default ? 'YES' : 'NO') . "\n";
    }

    $rem = Installment::where('user_id', $id)
        ->whereNotIn('status', ['paid', 'تم الدفع', 'canceled', 'cancelled', 'ملغي'])
        ->sum('remaining');
    echo " - Total Remaining Installments: $rem\n";
    echo "----------------------------------------\n";
}

echo "=== فحص نهائي دقيق للبيانات ===\n";
checkUser(10);
checkUser(16);
