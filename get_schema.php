<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$tbl = DB::select('SHOW CREATE TABLE model_has_permissions')[0]->{'Create Table'};
file_put_contents('model_has_permissions_schema.txt', $tbl);
echo "Schema saved to model_has_permissions_schema.txt\n";
