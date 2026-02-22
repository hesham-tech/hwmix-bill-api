<?php

use App\Models\CompanyUser;
use App\Models\User;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$brokenCompanyUsers = CompanyUser::whereNotExists(function ($query) {
    $query->select(DB::raw(1))
        ->from('users')
        ->whereRaw('users.id = company_user.user_id');
})->get();

echo "Broken CompanyUser records (missing user): " . $brokenCompanyUsers->count() . "\n";
foreach ($brokenCompanyUsers as $cu) {
    echo "ID: {$cu->id}, User ID: {$cu->user_id}\n";
}

$brokenUsers = User::whereNotNull('company_id')->whereNotExists(function ($query) {
    $query->select(DB::raw(1))
        ->from('companies')
        ->whereRaw('companies.id = users.company_id');
})->get();

echo "Users with broken company_id: " . $brokenUsers->count() . "\n";
