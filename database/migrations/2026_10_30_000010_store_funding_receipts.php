<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB};
return new class extends Migration {
    public function up(): void {
        Schema::create('store_funding_receipts',function(Blueprint $t){
            $t->id();$t->foreignId('account_id')->constrained('store_seller_accounts');$t->string('reference_hash',64);
            $t->unsignedBigInteger('amount_minor');$t->boolean('debt_only');$t->timestamp('credited_at');
            $t->unique(['account_id','reference_hash']);
        });
        Schema::create('store_funding_requests',function(Blueprint $t){
            $t->id();$t->string('operation_key',150)->unique();$t->foreignId('receipt_id')->constrained('store_funding_receipts');$t->timestamp('created_at');
        });
        DB::table('store_financial_journals')->where('kind','topup_received')->orderBy('id')->chunkById(100,function($rows){
            foreach($rows as $row){
                $meta=json_decode($row->metadata,true);if(empty($meta['reference'])||empty($meta['amount_minor']))continue;
                $account=DB::table('store_seller_accounts')->where('company_id',$meta['company_id']??0)->where('currency',$row->currency)->value('id');if(!$account)continue;
                $hash=hash('sha256',mb_strtoupper(preg_replace('/\s+/u','',trim($meta['reference']))));
                DB::table('store_funding_receipts')->insertOrIgnore(['account_id'=>$account,'reference_hash'=>$hash,'amount_minor'=>$meta['amount_minor'],'debt_only'=>$meta['debt_only']??false,'credited_at'=>$row->created_at]);
            }
        });
    }
    public function down(): void {Schema::dropIfExists('store_funding_requests');Schema::dropIfExists('store_funding_receipts');}
};
