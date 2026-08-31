<?php
require __DIR__.'/vendor/autoload.php';
 = require_once __DIR__.'/bootstrap/app.php';
 = ->make(Illuminate\Contracts\Console\Kernel::class);
->bootstrap();
 = new \Illuminate\Http\Request();
->merge(["include_self" => 1]);
echo ->boolean("include_self") ? "TRUE" : "FALSE";