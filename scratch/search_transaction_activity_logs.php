<?php

use App\Models\ActivityLog;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $count = ActivityLog::where('model', 'like', '%Transaction%')->count();
    echo "عدد سجلات الأنشطة المتعلقة بالمعاملات (Transaction) في جدول activity_logs: " . $count . "\n";

    if ($count > 0) {
        $logs = ActivityLog::where('model', 'like', '%Transaction%')
            ->latest()
            ->limit(10)
            ->get();
        foreach ($logs as $l) {
            echo "ID: {$l->id} | Action: {$l->action} | Model: {$l->model} | Description: {$l->description} | Created: {$l->created_at}\n";
        }
    } else {
        echo "لا توجد أي سجلات أنشطة للموديل Transaction في جدول activity_logs!\n";
    }
} catch (\Throwable $e) {
    echo "حدث خطأ:\n" . $e->getMessage() . "\n";
}
