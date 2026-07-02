<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::where('email', 'admin@admin.com')->first();
if ($user) {
    echo "Found user: ID={$user->id} | Email={$user->email} | CompanyID={$user->company_id}\n";
    $companies = App\Models\Company::all();
    echo "Total companies in DB: " . $companies->count() . "\n";
    foreach ($companies as $c) {
        echo "Company: ID={$c->id} | Name={$c->name}\n";
    }
} else {
    echo "User not found!\n";
}
