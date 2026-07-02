<?php

use Illuminate\Support\Facades\DB;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    if (\Illuminate\Support\Facades\Schema::hasTable('jobs')) {
        $count = DB::table('jobs')->count();
        echo "عدد الوظائف المعلقة في جدول jobs: " . $count . "\n";
        if ($count > 0) {
            $jobs = DB::table('jobs')->limit(5)->get();
            foreach ($jobs as $j) {
                echo "ID: {$j->id} | Queue: {$j->queue} | Payload: " . substr($j->payload, 0, 100) . "...\n";
            }
        }
    } else {
        echo "جدول jobs غير موجود في قاعدة البيانات.\n";
    }
} catch (\Throwable $e) {
    echo "حدث خطأ:\n" . $e->getMessage() . "\n";
}
