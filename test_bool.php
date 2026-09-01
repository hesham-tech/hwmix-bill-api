<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$request = new \Illuminate\Http\Request();
$request->merge(["include_self" => 1]);
echo $request->boolean("include_self") ? "TRUE" : "FALSE";