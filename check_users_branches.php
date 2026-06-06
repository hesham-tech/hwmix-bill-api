<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $total = User::withoutGlobalScopes()->count();
    $nullBranchCount = User::withoutGlobalScopes()->whereNull('branch_id')->count();

    echo "العدد الإجمالي للمستخدمين: " . $total . "\n";
    echo "عدد المستخدمين الذين يمتلكون branch_id بقيمة NULL: " . $nullBranchCount . "\n";

    if ($total > 0) {
        echo "\nتوزيع المستخدمين حسب الفرع (branch_id):\n";
        $byBranch = User::withoutGlobalScopes()
            ->select('branch_id', DB::raw('count(*) as total'))
            ->groupBy('branch_id')
            ->get();
        foreach ($byBranch as $item) {
            echo "الفرع ID: " . ($item->branch_id ?? 'NULL') . " -> العدد: " . $item->total . "\n";
        }
    }
} catch (\Throwable $e) {
    echo "حدث خطأ:\n" . $e->getMessage() . "\n";
}
