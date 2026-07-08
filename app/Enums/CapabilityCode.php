<?php

namespace App\Enums;

// سجل الكودات الثابتة للقدرات والسلوكيات التشغيلية (Capabilities Constants) لـ HWNix ERP.
class CapabilityCode
{
    const HAS_CASH_CUSTODY = 'has_cash_custody';
    const TRACK_RECEIVABLE = 'track_receivable';
    const TRACK_PAYABLE = 'track_payable';
    const IS_INTERNAL = 'is_internal';
    const CALCULATES_COMMISSION = 'calculates_commission';
    const CAN_RECEIVE_PAYMENTS = 'can_receive_payments';
    const CAN_ISSUE_INVOICES = 'can_issue_invoices';
    const CAN_OPEN_SHIFT = 'can_open_shift';
    const CAN_CLOSE_SHIFT = 'can_close_shift';
    const CAN_DELIVER_ORDERS = 'can_deliver_orders';
}
