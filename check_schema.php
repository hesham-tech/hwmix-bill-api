<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

$columns = Schema::getColumnListing('users');
echo "Columns of 'users' table:\n";
print_r($columns);

$columnsCU = Schema::getColumnListing('company_user');
echo "\nColumns of 'company_user' table:\n";
print_r($columnsCU);

$columnsCB = Schema::getColumnListing('cash_boxes');
echo "\nColumns of 'cash_boxes' table:\n";
print_r($columnsCB);
