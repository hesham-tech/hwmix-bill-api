<?php
namespace Modules\Store\Services;
use Illuminate\Support\Facades\{DB,Log};
use Illuminate\Support\Str;
use Modules\Store\Models\StoreOutbox;
class StoreOutboxProcessor {
    public function processId(int $id): bool {
        $entry=DB::transaction(function() use($id) {
            $entry=StoreOutbox::lockForUpdate()->find($id);
            if(!$entry||in_array($entry->status,['completed','manual_review'],true)||$entry->available_at->isFuture()
                ||($entry->status==='processing'&&$entry->locked_until?->isFuture())) return null;
            if($entry->attempts>=10) { $entry->update(['status'=>'manual_review','last_error'=>'انتهت المحاولات الآلية.']); return null; }
            $entry->update(['status'=>'processing','locked_until'=>now('UTC')->addMinutes(2),'lock_token'=>(string)Str::uuid(),'attempts'=>$entry->attempts+1]);
            return $entry;
        },3);
        if(!$entry) return false;
        try {
            $result=match($entry->kind) {
                'account.email_verification'=>$this->notifyVerification($entry),
                'notification.store_email'=>app(\Modules\Notification\Services\StoreWorkflowDelivery::class)->deliver($entry),
                'notification.erp_scheduled'=>app(\Modules\Notification\Services\ErpScheduledWorkflowDispatcher::class)->deliver($entry),
                'order.customer_notification'=>$this->notifyCustomer($entry),
                'order.vendor_notification'=>$this->notifyVendor($entry),
                'shipment.create'=>app(StoreShipmentService::class)->createForSubOrder($entry->payload['sub_order_id']),
                'refund.process'=>app(StoreRefundService::class)->process(\Modules\Store\Models\StoreRefund::findOrFail($entry->payload['refund_id'])),
                'invoice.create'=>app(StoreInvoiceBridge::class)->create($entry->payload['sub_order_id']),
                'return.accounting'=>app(StoreReturnService::class)->accounting($entry->payload['return_id']),
                'stats.daily'=>$this->dailyStatistics($entry),
                default=>'manual_review',
            };
            StoreOutbox::whereKey($entry->id)->where('lock_token',$entry->lock_token)->update([
                'status'=>$result==='manual_review'?'manual_review':($result==='pending'?'pending':'completed'),'locked_until'=>null,'lock_token'=>null,
                'available_at'=>$result==='pending'?now('UTC')->addMinutes(5):$entry->available_at,
                'last_error'=>$result==='manual_review'?($entry->kind==='notification.erp_scheduled'&&($entry->payload['channel']??null)==='whatsapp'
                    ?'إرسال واتساب مجدول يحتاج موافقة المستلم وقالب مزود معتمد؛ لم تُرسل رسالة نصية آلية.'
                    :'تحتاج هذه العملية مراجعة الإدارة.'):null,
            ]);
        } catch(\Throwable $error) {
            StoreOutbox::whereKey($entry->id)->where('lock_token',$entry->lock_token)->update([
                'status'=>$entry->attempts>=10?'manual_review':'pending','locked_until'=>null,'lock_token'=>null,
                'available_at'=>now('UTC')->addSeconds(min(3600,30*(2**min($entry->attempts,7)))),
                'last_error'=>'تعذرت المعالجة: '.class_basename($error),
            ]);
            Log::warning('Store outbox processing deferred',['outbox_id'=>$entry->id,'kind'=>$entry->kind,'exception_class'=>get_class($error)]);
        }
        return true;
    }
    private function notifyVerification(StoreOutbox $entry): string { app(StoreNotificationService::class)->verification($entry->payload); return 'completed'; }
    private function notifyCustomer(StoreOutbox $entry): string { app(StoreNotificationService::class)->customer($entry); return 'completed'; }
    private function notifyVendor(StoreOutbox $entry): string { app(StoreNotificationService::class)->vendor($entry); return 'completed'; }
    private function dailyStatistics(StoreOutbox $entry): string {
        (new \App\Jobs\UpdateDailySalesSummary($entry->payload['date'],(int)$entry->payload['company_id']))->handle();
        return 'completed';
    }
    public function drain(int $limit=10,int $seconds=20): int {
        $started=microtime(true); $processed=0;
        while($processed<$limit&&microtime(true)-$started<$seconds) {
            $id=StoreOutbox::where('available_at','<=',now('UTC'))->where(function($q) {
                $q->where('status','pending')->orWhere(fn($q)=>$q->whereIn('status',['processing','queued'])->where('locked_until','<=',now('UTC')));
            })->orderByRaw("CASE WHEN kind IN ('refund.process','shipment.create','invoice.create','return.accounting') THEN 0 WHEN kind = 'account.email_verification' THEN 1 WHEN kind = 'notification.erp_scheduled' THEN 3 ELSE 2 END")->orderBy('id')->value('id');
            if(!$id) break;
            if(config('queue.default')==='sync') {
                if($this->processId($id)) $processed++; else break;
            } else {
                $claimed=StoreOutbox::whereKey($id)->where(fn($q)=>$q->where('status','pending')->orWhere('locked_until','<=',now('UTC')))
                    ->update(['status'=>'queued','locked_until'=>now('UTC')->addMinutes(5)]);
                if(!$claimed) break;
                try { \Modules\Store\Jobs\ProcessStoreOutboxEntry::dispatch($id); }
                catch(\Throwable $error) { StoreOutbox::whereKey($id)->where('status','queued')->update(['status'=>'pending','locked_until'=>null]);throw $error; }
                $processed++;
            }
        }
        return $processed;
    }
}
