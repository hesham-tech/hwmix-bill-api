<?php

use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    // التحقق من العدد الإجمالي بدون فلاتر الشركة (بما أن Global Scopes قد تخفي بعضها)
    $totalCountWithoutScopes = Transaction::withoutGlobalScopes()->count();
    $totalCountWithScopes = Transaction::count();

    echo "العدد الإجمالي للمعاملات بدون فلاتر (withoutGlobalScopes): " . $totalCountWithoutScopes . "\n";
    echo "العدد الإجمالي للمعاملات مع الفلاتر النشطة: " . $totalCountWithScopes . "\n";

    if ($totalCountWithoutScopes > 0) {
        echo "\nتوزيع المعاملات حسب الشركة (company_id):\n";
        $byCompany = Transaction::withoutGlobalScopes()
            ->select('company_id', DB::raw('count(*) as total'))
            ->groupBy('company_id')
            ->get();
        foreach ($byCompany as $item) {
            echo "الشركة ID: " . ($item->company_id ?? 'NULL') . " -> العدد: " . $item->total . "\n";
        }

        echo "\nآخر 5 معاملات مسجلة في قاعدة البيانات:\n";
        $latest = Transaction::withoutGlobalScopes()->latest()->limit(5)->get();
        foreach ($latest as $t) {
            echo "ID: {$t->id} | User: {$t->user_id} | Type: {$t->type} | Amount: {$t->amount} | Company: " . ($t->company_id ?? 'NULL') . " | Created: {$t->created_at}\n";
        }
    } else {
        echo "جدول transactions فارغ تماماً في قاعدة البيانات!\n";
    }

} catch (\Throwable $e) {
    echo "حدث خطأ أثناء فحص قاعدة البيانات:\n";
    echo $e->getMessage() . "\n";
}
