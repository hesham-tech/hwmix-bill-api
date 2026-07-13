<?php

namespace App\Enums;

/**
 * الحالات المعتمدة لآلة حالات الخزنة النقدية (CashBox State Machine States)
 */
enum CashBoxStatus: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case ARCHIVED = 'archived';
    case LOCKED = 'locked';
}
