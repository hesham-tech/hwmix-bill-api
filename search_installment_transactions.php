<?php

use App\Models\Transaction;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $count = Transaction::withoutGlobalScopes()
        ->where(function ($q) {
            $q->where('description', 'like', '%أقساط%')
              ->orWhere('description', 'like', '%قسط%')
              ->orWhere('description', 'like', '%سداد%')
              ->orWhere('description', 'like', '%دفعة%');
        })
        ->count();

    echo "عدد المعاملات التي تحتوي على كلمات متعلقة بالأقساط أو السداد: " . $count . "\n";

    if ($count > 0) {
        $records = Transaction::withoutGlobalScopes()
            ->where(function ($q) {
                $q->where('description', 'like', '%أقساط%')
                  ->orWhere('description', 'like', '%قسط%')
                  ->orWhere('description', 'like', '%سداد%')
                  ->orWhere('description', 'like', '%دفعة%');
            })
            ->latest()
            ->limit(10)
            ->get();
            
        foreach ($records as $r) {
            echo "ID: {$r->id} | User: {$r->user_id} | Type: {$r->type} | Amount: {$r->amount} | Description: {$r->description} | Created: {$r->created_at}\n";
        }
    } else {
        echo "لا توجد أي معاملة في جدول transactions تحتوي على كلمات متعلقة بالأقساط أو السداد!\n";
    }
} catch (\Throwable $e) {
    echo "حدث خطأ:\n" . $e->getMessage() . "\n";
}
