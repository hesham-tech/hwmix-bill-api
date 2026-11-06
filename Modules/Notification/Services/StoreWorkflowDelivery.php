<?php
namespace Modules\Notification\Services;
use App\Models\{User,Company};
use Modules\Notification\Models\{NotificationWorkflow,NotificationWorkflowStep};
use Modules\Store\Models\StoreOutbox;
use Modules\Store\Services\StoreOutboxService;
class StoreWorkflowDelivery {
    public function queue(string $event,string $recipientKind,int $recipientId,int $companyId,array $variables,string $key): bool {
        $master=(int)config('app.master_company_id',1);
        $base=NotificationWorkflow::withoutGlobalScopes()->whereNull('deleted_at')->where('event_type',$event)->where('is_active',true);
        $workflows=(clone $base)->where('company_id',$companyId)->get();
        if($workflows->isEmpty())$workflows=(clone $base)->where('is_global',true)->where(fn($q)=>$q->whereNull('company_id')->orWhere('company_id',$master))->get();
        foreach($workflows as $workflow)foreach($workflow->steps()->where('is_active',true)->with('template')->get() as $step){
            $channels=$step->channel??$step->getRawOriginal('channel');if(is_string($channels))$channels=$channels==='both'?['email','whatsapp']:[$channels];
            if(!in_array('email',(array)$channels,true)||!$this->allowed($step))continue;
            app(StoreOutboxService::class)->enqueue('notification.store_email','store-mail:'.hash('sha256',$key.':'.$step->id),[
                'step_id'=>$step->id,'recipient_kind'=>$recipientKind,'recipient_id'=>$recipientId,'company_id'=>$companyId,'variables'=>$variables,
            ],now('UTC')->addDays(max(0,min(365,(int)$step->delay_days))));
        }
        return $workflows->isNotEmpty();
    }
    private function allowed(NotificationWorkflowStep $step): bool {
        $template=$step->template;if(!$template||!$template->is_active||!$step->workflow)return false;
        return (int)$template->company_id===(int)$step->workflow->company_id||($template->is_global&&($template->company_id===null||(int)$template->company_id===(int)config('app.master_company_id',1)));
    }
    public function deliver(StoreOutbox $entry): string {
        $p=$entry->payload;$step=NotificationWorkflowStep::with(['template','workflow'])->find($p['step_id']);
        if(!$step||!$step->is_active||!$step->workflow?->is_active||!$this->allowed($step))return 'completed';
        $channels=$step->channel??$step->getRawOriginal('channel');if(is_string($channels))$channels=$channels==='both'?['email','whatsapp']:[$channels];
        if(!in_array('email',(array)$channels,true))return 'completed';
        $recipient=$p['recipient_kind']==='customer'?User::withoutGlobalScopes()->find($p['recipient_id']):Company::withoutGlobalScopes()->whereNull('deleted_at')->find($p['recipient_id']);
        if(!$recipient||!$recipient->email||($p['recipient_kind']==='customer'&&!$recipient->email_verified_at))return 'completed';
        $variables=$p['variables'];$variables['{customer_name}']=$p['recipient_kind']==='customer'?($recipient->full_name?:$recipient->nickname):($variables['{customer_name}']??'');
        $html=[];$plain=[];foreach($variables as $key=>$value){$html[$key]=e((string)$value);$plain[$key]=str_replace(["\r","\n"],' ',strip_tags((string)$value));}
        $subject=strtr($step->template->subject?:'تحديث طلب المتجر',$plain);$body=strtr($step->template->body,$html);
        app(WorkflowProcessor::class)->sendTransactionalEmail((int)$p['company_id'],$recipient->email,$subject,$body,$entry->deduplication_key);
        return 'completed';
    }
}
