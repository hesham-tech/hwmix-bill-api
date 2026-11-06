<?php
namespace Modules\Store\Services;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
final class StoreTaskScan {
    public function next(Builder $query,string $key,int $limit) {
        $cursor=(int)Cache::get('store:cursor:'.$key,0);
        $rows=(clone $query)->where('id','>',$cursor)->orderBy('id')->limit($limit)->get();
        return $rows->isEmpty()&&$cursor?(clone $query)->orderBy('id')->limit($limit)->get():$rows;
    }
    public function advance(string $key,int $id): void { Cache::put('store:cursor:'.$key,$id,now('UTC')->addDays(7)); }
    public function review(string $stage,int $id,\Throwable $error): void {
        app(StoreOutboxService::class)->enqueue('operations.review','review:'.$stage.':'.$id.':'.now('UTC')->toDateString(),
            ['stage'=>$stage,'record_id'=>$id,'exception_class'=>get_class($error)])->update(['status'=>'manual_review','last_error'=>'تحتاج هذه العملية مراجعة الإدارة. لم تتوقف بقية العمليات.']);
        \Illuminate\Support\Facades\Log::warning('Store scheduled task requires review',['stage'=>$stage,'record_id'=>$id,'exception_class'=>get_class($error)]);
    }
}
