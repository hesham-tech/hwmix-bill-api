<?php
namespace Modules\Store\Services;
use App\Models\{Company,User};
use Illuminate\Support\Facades\DB;
use Modules\Notification\Services\{WorkflowProcessor,StoreWorkflowDelivery};
use Modules\Store\Models\{StoreOrder,StoreEmailVerification,StoreOutbox};
class StoreNotificationService {
    private function send(string $email,string $subject,string $html,string $key,?int $companyId=null): void {
        app(WorkflowProcessor::class)->sendTransactionalEmail($companyId??(int)config('app.master_company_id',1),$email,$subject,$html,$key);
    }
    private function dashboard(string $key,string $type,string $title,string $message,?int $userId,?int $companyId,?int $orderId): void {
        DB::table('store_notifications')->insertOrIgnore(['deduplication_key'=>$key,'type'=>$type,'title'=>$title,'message'=>$message,
            'user_id'=>$userId,'company_id'=>$companyId,'store_order_id'=>$orderId,'created_at'=>now('UTC'),'updated_at'=>now('UTC')]);
    }
    public function verification(array $payload): void {
        $verification=StoreEmailVerification::find($payload['verification_id']);
        if(!$verification||$verification->verified_at||$verification->expires_at->isPast()||!hash_equals($verification->token_hash,hash_hmac('sha256',$payload['code'],config('app.key'))))return;
        $user=User::withoutGlobalScopes()->find($verification->user_id);
        if(!$user||$user->email!==$verification->email)return;
        $this->send($verification->email,'رمز توثيق بريد المتجر','<p dir="rtl">رمز توثيق بريدك: <strong>'.e($payload['code']).'</strong>. تنتهي صلاحيته خلال 15 دقيقة.</p>','verification:'.$verification->id.':'.$verification->token_hash);
    }
    public function customer(StoreOutbox $entry): void {
        $p=$entry->payload;$order=StoreOrder::with('customer')->findOrFail($p['order_id']);$title='تحديث طلب المتجر '.$order->order_number;
        $master=(int)config('app.master_company_id',1);
        $this->dashboard($entry->deduplication_key.':admin',$p['event'],$title,'تحديث على طلب المتجر.',null,$master,$order->id);
        if(!$order->customer_user_id)return;
        $this->dashboard($entry->deduplication_key.':dashboard',$p['event'],$title,'راجع تفاصيل الطلب لمتابعة آخر تحديث.',$order->customer_user_id,null,$order->id);
        if(!$order->customer?->email_verified_at)return;
        $event=$p['event']==='placed'?'created':($p['event']==='refunded'?'refunded':'updated');
        $url=rtrim(config('store.frontend_url'),'/').'/store/order-confirmation/'.rawurlencode($order->order_number);
        $labels=['placed'=>'استلام الطلب','confirmed'=>'تأكيد الطلب','processing'=>'تجهيز الطلب','shipped'=>'تسليم الشحنة للناقل','delivered'=>'تسليم الطلب','partial_cancellation'=>'إلغاء جزء من الطلب','refunded'=>'تحديث الاسترداد'];
        $vars=['{order_number}'=>$order->order_number,'{order_total}'=>(string)$order->total_amount,'{currency}'=>$order->currency,
            '{customer_name}'=>$order->customer->full_name,'{order_url}'=>$url,'{event_name}'=>$labels[$p['event']]??'تحديث الطلب'];
        if(!app(StoreWorkflowDelivery::class)->queue('store.customer.'.$event,'customer',$order->customer_user_id,$master,$vars,$entry->deduplication_key))
            $this->send($order->customer->email,$title,'<p dir="rtl">يوجد تحديث على طلبك <strong>'.e($order->order_number).'</strong>. يمكنك متابعة التفاصيل من قسم طلباتي في المتجر.</p>',$entry->deduplication_key,$master);
    }
    public function vendor(StoreOutbox $entry): void {
        $p=$entry->payload;$company=Company::withoutGlobalScopes()->whereNull('deleted_at')->findOrFail($p['company_id']);
        $event=$p['event']??'order.created';$title=$event==='return_requested'?'طلب إرجاع منتج':($event==='order.created'?'طلب متجر جديد':'تحديث طلب المتجر');
        $members=app(StoreVendorRecipients::class)->members($company->id);
        if($members->isEmpty())$this->dashboard($entry->deduplication_key.':dashboard',$event,$title,'راجع طلبات المتجر لمتابعة التحديث.',null,$company->id,$p['order_id']);
        foreach($members as $member)$this->dashboard($entry->deduplication_key.':member:'.$member->id,$event,$title,'راجع طلبات شركتك لمتابعة التحديث.',$member->id,$company->id,$p['order_id']);
        foreach($members->filter(fn($m)=>filter_var($m->email,FILTER_VALIDATE_EMAIL))->unique(fn($m)=>mb_strtolower(trim($m->email))) as $member){
            if(mb_strtolower(trim($member->email))===mb_strtolower(trim((string)$company->email)))continue;
            app(StoreOutboxService::class)->enqueue('order.vendor_member_notification',$entry->deduplication_key.':mail-member:'.$member->id,
                ['company_id'=>$company->id,'user_id'=>$member->id,'order_id'=>$p['order_id'],'sub_order_id'=>$p['sub_order_id']??null,'title'=>$title]);
        }
        if(!$company->email)return;
        $order=StoreOrder::findOrFail($p['order_id']);$canonical=match($event){'order.created'=>'created','return_requested'=>'return_requested','return_to_origin'=>'return_to_origin',default=>'updated'};
        $vars=['{company_name}'=>$company->name,'{order_number}'=>$order->order_number,'{order_url}'=>rtrim(config('store.frontend_url'),'/').'/app/my-store','{event_name}'=>$title];
        if(!app(StoreWorkflowDelivery::class)->queue('store.vendor.'.$canonical,'vendor',$company->id,$company->id,$vars,$entry->deduplication_key))
            $this->send($company->email,$title,'<p dir="rtl">يوجد تحديث في متجر '.e($company->name).'. افتح لوحة طلبات المتجر لمتابعته.</p>',$entry->deduplication_key,$company->id);
    }
    public function vendorMember(StoreOutbox $entry): string {
        $p=$entry->payload;$user=User::withoutGlobalScopes()->find($p['user_id']);
        if(!$user||!app(StoreVendorRecipients::class)->eligible($user,(int)$p['company_id'])||!filter_var($user->email,FILTER_VALIDATE_EMAIL))return 'completed';
        $order=StoreOrder::find($p['order_id']);if(!$order||!$order->subOrders()->where('company_id',$p['company_id'])->exists())return 'completed';
        $this->send($user->email,$p['title'],'<p dir="rtl">يوجد تحديث لطلب شركتك. افتح قسم متجر شركتي لمتابعته.</p>',$entry->deduplication_key,(int)$p['company_id']);
        return 'completed';
    }
}
