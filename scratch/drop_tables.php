<?php
// ملف مساعد نهائي لحذف الجدولين المتبقيين وتشغيل الترحيل بنجاح.

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=0;');
    echo "Foreign key checks disabled.\n";
    
    echo "Dropping companies and migrations tables...\n";
    Illuminate\Support\Facades\DB::statement("DROP TABLE IF EXISTS companies, migrations;");
    echo "DROP SUCCESSFUL\n";
    
    echo "Running migrate...\n";
    Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
    echo "MIGRATION SUCCESSFUL\n";
    echo Illuminate\Support\Facades\Artisan::output();
    
    Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    echo "Foreign key checks re-enabled.\n";
} catch (\Exception $e) {
    echo "Failed: " . $e->getMessage() . "\n";
    try { Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=1;'); } catch (\Exception $ex) {}
}
