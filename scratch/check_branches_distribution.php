<?php

use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $total = Transaction::withoutGlobalScopes()->count();
    $nullBranchCount = Transaction::withoutGlobalScopes()->whereNull('branch_id')->count();

    echo "العدد الإجمالي للمعاملات: " . $total . "\n";
    echo "عدد المعاملات التي تمتلك branch_id بقيمة NULL: " . $nullBranchCount . "\n";

    if ($total > 0) {
        echo "\nتوزيع المعاملات حسب الفرع (branch_id):\n";
        $byBranch = Transaction::withoutGlobalScopes()
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
