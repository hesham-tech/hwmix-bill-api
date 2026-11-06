<?php
namespace Modules\Store\Services;
class StorePolicyRules {
    public static function rules(string $prefix,bool $nullable=false): array {
        $optional=$nullable?'nullable|':'sometimes|';
        return [
            $prefix.'funding_mode'=>$optional.'in:prepaid,credit',
            $prefix.'rto_fee_payer'=>$optional.'in:platform,seller',
            $prefix.'credit_limit_minor'=>$optional.'integer|min:0|max:999999999999999',
            $prefix.'commission_type'=>$optional.'in:percent,fixed,mixed,exempt',
            $prefix.'commission_percent'=>$optional.'decimal:0,2|min:0|max:100',
            $prefix.'commission_fixed_minor'=>$optional.'integer|min:0|max:999999999999999',
            $prefix.'commission_timing'=>$optional.'in:delivered_collected,remitted,after_return_window',
            $prefix.'use_sales_balance_for_commission'=>$optional.'boolean',
            $prefix.'automatic_debt_offset'=>$optional.'boolean',
            $prefix.'settlement_delay_days'=>$optional.'integer|min:0|max:365',
            $prefix.'minimum_payout_minor'=>$optional.'integer|min:0|max:999999999999999',
            $prefix.'automatic_payout'=>$optional.'boolean',
            $prefix.'payout_method'=>$optional.'in:manual',
            $prefix.'payout_weekday'=>$optional.'integer|min:0|max:6',
            $prefix.'payout_hour'=>$optional.'integer|min:0|max:23',
        ];
    }
}
