<?php
require dirname(__DIR__,2).'/vendor/autoload.php';
$app=require dirname(__DIR__,2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(!app()->environment('testing') || config('database.default')!=='mysql' || !str_starts_with(config('database.connections.mysql.database'),'hwnix_store_test_'))exit(90);
fwrite(STDOUT,"ready\n");
for($i=0;$i<200&&!is_file($argv[3]);$i++)usleep(100000);
if(!is_file($argv[3]))exit(91);
try {
 if($argv[1]==='payout')app(Modules\Store\Services\StorePayoutService::class)->request((int)$argv[2],10000,Illuminate\Support\Str::uuid()->toString());
 else Illuminate\Support\Facades\DB::transaction(function()use($argv){
  $sub=Modules\Store\Models\StoreSubOrder::findOrFail((int)$argv[2]);
  app(Modules\Store\Services\StockReservationService::class)->reserveForSubOrder($sub,$sub->items,(int)$argv[4]);
 },3);
 echo "accepted";
} catch(Illuminate\Validation\ValidationException $error){echo "rejected";}
