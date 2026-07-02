<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $permissions = \Spatie\Permission\Models\Permission::where('name', 'like', '%transactions%')->get();
    echo "الأذونات المتعلقة بالمعاملات في قاعدة البيانات:\n";
    foreach ($permissions as $p) {
        echo "ID: {$p->id} | Name: {$p->name} | Guard: {$p->guard_name}\n";
    }
} catch (\Throwable $e) {
    echo "حدث خطأ:\n" . $e->getMessage() . "\n";
}
