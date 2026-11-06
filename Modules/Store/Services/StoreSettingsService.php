<?php
namespace Modules\Store\Services;
use Modules\Store\Models\StoreSetting;

class StoreSettingsService
{
    public function defaults(): array {
        return [
            'enabled'=>false,'currency'=>'EGP','cod_enabled'=>true,'online_enabled'=>false,
            'allow_guest_checkout'=>false,'require_verified_email'=>true,
            'max_unconfirmed_orders_per_customer'=>3,
            'hold_hours'=>24,'quote_minutes'=>15,'auto_confirm_paid_orders'=>true,
            'platform_shipping_account_id'=>null,'legal_document_ids'=>[],
            'tax_mode'=>'unconfigured','default_tax_rate_bps'=>0,'legal_setup_confirmed'=>false,
            'return_policy'=>['window_days'=>null,'allow_change_of_mind'=>true,'allow_defect'=>true],
            'seller_policy'=>[
                'funding_mode'=>'prepaid','credit_limit_minor'=>0,'commission_type'=>'percent',
                'commission_percent'=>'0.00','commission_fixed_minor'=>0,'commission_timing'=>'delivered_collected',
                'use_sales_balance_for_commission'=>true,'automatic_debt_offset'=>true,
                'settlement_delay_days'=>0,'minimum_payout_minor'=>0,'automatic_payout'=>false,
                'payout_method'=>'manual','payout_weekday'=>1,'payout_hour'=>12,'rto_fee_payer'=>'platform',
            ],
        ];
    }
    public function get(): array {
        return array_replace_recursive($this->defaults(),StoreSetting::where('key','marketplace')->value('value')??[]);
    }
    public function save(array $settings): array {
        StoreSetting::updateOrCreate(['key'=>'marketplace'],['value'=>array_replace_recursive($this->get(),$settings)]);
        return $this->get();
    }
}
