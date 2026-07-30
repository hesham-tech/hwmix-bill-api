<?php
// تجميعة سياسات تنفيذ استدعاءات الذكاء الاصطناعي واختيار نطاق الحسابات.

namespace Modules\AiPlatform\Enums;

enum ExecutionPolicy: string
{
    case SYSTEM_ONLY = 'system_only';   // الحسابات التابعة للنظام للبنية التحتية فقط
    case COMPANY_ONLY = 'company_only'; // حسابات الشركة فقط
    case COMPANY_FIRST = 'company_first'; // حسابات الشركة أولاً ثم النظام عند عدم التوفر
    case SHARED = 'shared';             // الحسابات المتاحة والمشتركة للنظام والشركات
}
