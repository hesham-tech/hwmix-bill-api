<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$request = Illuminate\Http\Request::create('/api/v1/analytics/top-products?sort_by=total_sold_quantity&limit=10&period=month', 'GET');
$request->headers->set('Accept', 'application/json');
Illuminate\Support\Facades\Auth::loginUsingId(1);
$response = $app->make(Illuminate\Contracts\Http\Kernel::class)->handle($request);
echo $response->getContent();
