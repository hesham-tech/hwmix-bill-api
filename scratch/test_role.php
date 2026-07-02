<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $role = \App\Models\Role::firstOrCreate(
        ['name' => 'test_role_1234', 'company_id' => 2],
        ['guard_name' => 'web', 'created_by' => 1, 'label' => 'Test Role']
    );
    echo "Success: Role ID " . $role->id . "\n";
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
