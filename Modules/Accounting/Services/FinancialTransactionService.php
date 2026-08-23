<?php

namespace Modules\Accounting\Services;

use Modules\Core\Services\DocumentServiceInterface;
use Modules\Sales\Models\Invoice;
use App\Contracts\FinancialEngineInterface;
use Exception;

/**
 * خدمة تسجيل المعاملات المالية المباشرة (سندات القبض والصرف).
 * تعمل كـ Adapter يحول الطلبات إلى FinancialEngine.
 */
class FinancialTransactionService implements DocumentServiceInterface
{
    public function create(array $data): Invoice|array
    {
        $type = $data['invoice_type_code'] ?? $data['type'] ?? 'receipt';
        $amount = (float)($data['total_amount'] ?? $data['amount'] ?? 0);
        $cashBoxId = $data['cash_box_id'] ?? $data['cashbox_id'] ?? null;
        $userId = $data['user_id'] ?? null;
        $companyId = $data['company_id'] ?? null;
        $description = $data['notes'] ?? $data['description'] ?? "سند {$type}";

        if (!$cashBoxId || !$userId || !$companyId) {
            throw new Exception("بيانات السند غير مكتملة (يجب تحديد الخزينة، العميل/المورد، والشركة).");
        }

        $engine = app(FinancialEngineInterface::class);
        $operationId = (string) \Illuminate\Support\Str::uuid();

        $metadata = [
            'description' => $description,
            'operation_id' => $operationId,
        ];

        if ($type === 'receipt') {
            $operationId = $engine->recordStandaloneReceipt($amount, $cashBoxId, $userId, $companyId, $metadata);
        } elseif ($type === 'payment') {
            $operationId = $engine->recordStandalonePayment($amount, $cashBoxId, $userId, $companyId, $metadata);
        } else {
            throw new Exception("نوع المستند [{$type}] غير مدعوم في المسار المالي الحالي.");
        }

        return [
            'financial_operation_id' => $operationId,
            'type' => $type,
            'amount' => $amount
        ];
    }

    public function update(array $data, Invoice $invoice): Invoice
    {
        throw new Exception("تعديل السندات المباشرة غير مدعوم. يجب إلغاء السند وإنشاء سند جديد.");
    }

    public function cancel(Invoice $invoice): Invoice
    {
        // Cancel logic if needed, but not implemented for standalone yet
        throw new Exception("إلغاء السندات المباشرة غير مدعوم في هذه النسخة.");
    }
}
