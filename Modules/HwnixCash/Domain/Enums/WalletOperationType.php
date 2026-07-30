<?php
// التعداد المخصص لأنواع عمليات المحافظ الإلكترونية.

namespace Modules\HwnixCash\Domain\Enums;

enum WalletOperationType: string
{
    case TRANSFER = 'transfer';
    case RECEIVE = 'receive';
    case BILL_PAYMENT = 'bill_payment';
    case CASH_WITHDRAW = 'cash_withdraw';
    case CASH_DEPOSIT = 'cash_deposit';
    case MERCHANT_PAYMENT = 'merchant_payment';
    case CARD_PURCHASE = 'card_purchase';
    case REFUND = 'refund';
    case REVERSAL = 'reversal';
    case BALANCE_INQUIRY = 'balance_inquiry';
    case RECONCILIATION = 'reconciliation';
    case OTHER = 'other';
}
