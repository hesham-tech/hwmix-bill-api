<?php
// التعداد المخصص لمزودي خدمة المحافظ الإلكترونية والبنوك.

namespace Modules\HwnixCash\Domain\Enums;

enum WalletProvider: string
{
    case VODAFONE_CASH = 'vodafone_cash';
    case ORANGE_CASH = 'orange_cash';
    case ETISALAT_CASH = 'etisalat_cash';
    case WE_PAY = 'we_pay';
    case INSTAPAY = 'instapay';
    case BANK = 'bank';
    case FAWRY = 'fawry';
    case OTHER = 'other';
}
