<?php
namespace Modules\Payment\Actions;
use App\Models\CompanySubscription;
use Illuminate\Validation\ValidationException;
use Modules\Core\Actions\BaseAction;
use Modules\Payment\Models\PaymentGateway;
use Modules\Payment\Services\PaymentSessionService;

class ProcessPaymentAction extends BaseAction
{
    public function handle(array $data=[]): array
    {
        $this->authorize('companies.update');
        if(($data['payable_type']??'')!==CompanySubscription::class)
            throw ValidationException::withMessages(['payable_type'=>'يجب بدء دفع المتجر من الطلب نفسه.']);
        $subscription=CompanySubscription::query()->where('company_id',auth()->user()->active_company_id)->findOrFail($data['payable_id']);
        if($subscription->status!=='pending')
            throw ValidationException::withMessages(['payable_id'=>'هذا الاشتراك غير قابل للدفع.']);
        $gateway=PaymentGateway::withoutGlobalScopes()->whereNull('deleted_at')
            ->where('company_id',config('app.master_company_id',1))->findOrFail($data['payment_gateway_id']);
        $user=auth()->user();
        $parts=preg_split('/\s+/u',trim($user->full_name??''),2);
        $options=['billing_data'=>['first_name'=>$parts[0]?:'NA','last_name'=>$parts[1]??'NA',
            'email'=>$user->email?:'NA','phone_number'=>$user->phone?:'NA']];
        // Return URLs are server-owned; client-provided redirects and billing data are never trusted.
        return app(PaymentSessionService::class)->start($subscription,$gateway,(string)$subscription->price,
            strtoupper($subscription->plan?->currency??'EGP'),$data['idempotency_key'],$options);
    }
}
