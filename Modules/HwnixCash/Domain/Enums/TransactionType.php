<?php
// إينوم أنواع المعاملات والعمليات المالية والتشغيلية في محرك تحليل الرسائل.

namespace Modules\HwnixCash\Domain\Enums;

enum TransactionType: string
{
    case RECEIVE = 'receive';
    case SEND = 'send';
    case WITHDRAW = 'withdraw';
    case DEPOSIT = 'deposit';
    case RECHARGE = 'recharge';
    case BALANCE = 'balance';
    case WRONG_PIN = 'wrong_pin';
    case NONE = 'none';
}
