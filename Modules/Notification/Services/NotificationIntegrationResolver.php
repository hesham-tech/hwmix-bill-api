<?php
namespace Modules\Notification\Services;
use Modules\Notification\Models\{MailSetting,WhatsAppSetting};
class NotificationIntegrationResolver {
    public function mail(?int $companyId=null): ?MailSetting {
        return $this->resolve(MailSetting::class,$companyId);
    }
    public function whatsapp(?int $companyId=null): ?WhatsAppSetting {
        return $this->resolve(WhatsAppSetting::class,$companyId);
    }
    private function resolve(string $model,?int $companyId) {
        $companyId=$companyId??(int)config('app.master_company_id',1);
        $master=(int)config('app.master_company_id',1);
        return $model::withoutGlobalScopes()->whereNull('deleted_at')->where('is_active',true)
            ->where(fn($q)=>$q->where('company_id',$companyId)->orWhere(fn($shared)=>$shared->where('is_global',true)->where(fn($owner)=>$owner->whereNull('company_id')->orWhere('company_id',$master))))
            ->orderByRaw('CASE WHEN company_id = ? THEN 0 WHEN company_id IS NULL THEN 1 ELSE 2 END',[$companyId])
            ->orderByDesc('is_default')->orderBy('id')->first();
    }
}
