<?php
namespace Modules\Notification\Services;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\{Cache,DB};
use Modules\Notification\Models\NotificationWorkflowStep;
use Modules\Sales\Models\Invoice;
use Modules\Store\Models\{StoreSetting,StoreOutbox};
use Modules\Store\Services\{StoreOutboxService,StoreTaskScan};
final class ErpScheduledWorkflowDispatcher {
 public function scan(int $limit=100,int $seconds=20): int {
  $limit=max(1,min(500,$limit));$seconds=max(1,min(30,$seconds));$started=microtime(true);$count=0;
  $lock=Cache::lock('notifications:erp:scan',60);if(!$lock->get())return 0;
  try {
   $today=CarbonImmutable::today(config('store.business_timezone','Africa/Cairo'));
   // First activation does not unexpectedly send years of historical reminders.
   $activation=StoreSetting::firstOrCreate(['key'=>'erp_notification_activation'],['value'=>['date'=>$today->toDateString()]]);
   $from=max($activation->value['date'],$today->subDays(7)->toDateString());
   $scan=app(StoreTaskScan::class);
   $steps=NotificationWorkflowStep::where('is_active',true)->whereHas('workflow',fn($q)=>$q->withoutGlobalScopes()->whereNull('deleted_at')->where('is_active',true)->whereNotNull('company_id')->whereIn('event_type',['invoice.due_soon','invoice.overdue']))
    ->with(['workflow'=>fn($q)=>$q->withoutGlobalScopes(),'template'=>fn($q)=>$q->withoutGlobalScopes()->whereNull('deleted_at')]);
   foreach($scan->next($steps,'erp:steps',100) as $step) {
    if($count >= $limit || microtime(true)-$started >= $seconds)break;
    $scan->advance('erp:steps',$step->id);
    $minimum=CarbonImmutable::parse($from)->subDays((int)$step->delay_days)->toDateString();
    $maximum=$today->subDays((int)$step->delay_days)->toDateString();
    $invoices=Invoice::withoutGlobalScopes()->whereNull('deleted_at')->where('company_id',$step->workflow->company_id)->where('is_marketplace_managed',false)
     ->whereNotIn('status',['draft','cancelled','canceled'])->whereIn('payment_status',['unpaid','partial','partially_paid'])->where('remaining_amount','>',0)
     ->where('due_date','>=',$minimum)->where('due_date','<=',$maximum.' 23:59:59');
    if($step->workflow->event_type==='invoice.due_soon')$invoices->where('due_date','>=',$today->toDateString());
    foreach($scan->next($invoices,'erp:step:'.$step->id,min(25,$limit-$count)) as $invoice) {
     if(microtime(true)-$started >= $seconds)break;
     $scan->advance('erp:step:'.$step->id,$invoice->id);
     $channels=$step->channel;if(is_string($channels))$channels=$channels==='both'?['email','whatsapp']:[$channels];
     foreach(array_intersect((array)$channels,['email','whatsapp']) as $channel) {
      $date=$invoice->due_date->toDateString();
      app(StoreOutboxService::class)->enqueue('notification.erp_scheduled','erp:reminder:'.$step->id.':'.$invoice->id.':'.$date.':'.$channel,
       ['step_id'=>$step->id,'invoice_id'=>$invoice->id,'due_date'=>$date,'channel'=>$channel]);
     }
     $count++;
    }
   }
   return $count;
  } finally {$lock->release();}
 }
 public function deliver(StoreOutbox $entry): string {
  $p=$entry->payload;$step=NotificationWorkflowStep::with(['workflow'=>fn($q)=>$q->withoutGlobalScopes(),'template'=>fn($q)=>$q->withoutGlobalScopes()->whereNull('deleted_at')])->find($p['step_id']);
  $invoice=Invoice::withoutGlobalScopes()->whereNull('deleted_at')->with(['customer'=>fn($q)=>$q->withoutGlobalScopes()])->find($p['invoice_id']);
  if(!$step?->is_active||!$step->workflow||$step->workflow->deleted_at||!$step->workflow->is_active||!$invoice
   ||!in_array($step->workflow->event_type,['invoice.due_soon','invoice.overdue'],true)
   ||$invoice->is_marketplace_managed||(int)$invoice->company_id!==(int)$step->workflow->company_id
   ||$invoice->due_date?->toDateString()!==$p['due_date']||!in_array($invoice->payment_status,['unpaid','partial','partially_paid'],true)
   ||bccomp((string)$invoice->remaining_amount,'0',2)<=0||in_array($invoice->status,['draft','cancelled','canceled'],true))return 'completed';
  $channels=$step->channel;if(is_string($channels))$channels=$channels==='both'?['email','whatsapp']:[$channels];
  if($step->workflow->event_type==='invoice.due_soon'&&$invoice->due_date->toDateString()<now(config('store.business_timezone','Africa/Cairo'))->toDateString())return 'completed';
  if(!in_array($p['channel'],(array)$channels,true))return 'completed';
  // Scheduled WhatsApp needs explicit opt-in and an approved provider template; no blind text retries.
  if($p['channel']==='whatsapp')return 'manual_review';
  return app(WorkflowProcessor::class)->deliverScheduledEmail($step,$invoice,$entry->deduplication_key);
 }
}
