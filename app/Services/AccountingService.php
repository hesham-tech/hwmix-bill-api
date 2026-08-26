<?php

namespace App\Services;

use App\Models\User;
use App\Models\Invoice;
use App\Models\FinancialOperation;
use App\Services\FinancialEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * خدمة الحسابات (AccountingService) - تعمل كـ Adapter متوافق مع دستور المعمارية المالية ومفوض لـ FinancialEngine.
 */
class AccountingService
{
    /**
     * تسجيل الأثر المالي لإنشاء فاتورة.
     */
    public function recordInvoiceCreation(Invoice $invoice, array $options = []): void
    {
        $payload = array_merge($options, [
            'operation_id' => $options['operation_id'] ?? (string) Str::uuid(),
        ]);
        app(FinancialEngine::class)->processInvoiceCreation($invoice, $payload);
    }

    /**
     * تسجيل حركة مالية (قبض أو صرف) — يُعيد operationId لربطه بالمستند المصدر.
     */
    public function recordPayment(int $companyId, User $staff, ?User $party, float $amount, string $direction, array $options = []): string
    {
        return DB::transaction(function () use ($companyId, $staff, $party, $amount, $direction, $options) {
            $operationId = $options['operation_id'] ?? (string) Str::uuid();
            $cashBoxId = $options['cash_box_id'] ?? null;
            
            $engine = app(FinancialEngine::class);
            $opService = app(FinancialOperationService::class);
            $ledgerService = app(FinancialLedgerService::class);
            
            $opType = $direction === 'in' ? 'payment_receipt' : 'payment_disbursement';
            
            $opService->createOperation([
                'id' => $operationId,
                'company_id' => $companyId,
                'type' => $opType,
                'amount' => $amount,
                'source_type' => $options['source_type'] ?? null,
                'source_id' => $options['source_id'] ?? null,
                'metadata' => $options,
            ]);

            // 1. معالجة رصيد الخزينة
            if ($direction === 'in') {
                $engine->receiveMoney($amount, $cashBoxId, $operationId, $options);
            } else {
                $engine->payMoney($amount, $cashBoxId, $operationId, $options);
            }

            // 2. معالجة ذمة الطرف الآخر
            if ($party && !($options['skip_party_balance'] ?? false)) {
                $invoiceId = $options['source_invoice_id'] ?? $options['invoice_id'] ?? null;
                $invoice = $invoiceId ? Invoice::withoutGlobalScopes()->find($invoiceId) : null;
                $relationType = ($invoice && in_array($invoice->invoice_type_code, ['purchase', 'purchase_return'])) ? 'payable' : 'receivable';
                
                if ($relationType === 'payable') {
                    if ($direction === 'in') {
                        $engine->createPayable($party, $amount, $operationId, $options);
                    } else {
                        $engine->reducePayable($party, $amount, $operationId, array_merge($options, ['allow_negative' => true]));
                    }
                } else {
                    if ($direction === 'in') {
                        $engine->reduceReceivable($party, $amount, $operationId, array_merge($options, ['allow_negative' => true]));
                    } else {
                        $engine->createReceivable($party, $amount, $operationId, $options);
                    }
                }
            }

            // 3. ترحيل قيود الأستاذ العام
            $ledgerService->recordEntry(
                $staff,
                'asset',
                $amount,
                $direction === 'in' ? 'debit' : 'credit',
                $options['description'] ?? ($direction === 'in' ? 'قبض نقدي' : 'صرف نقدي'),
                now(),
                $operationId
            );

            // ترحيل الجانب المقابل لضمان القيد المزدوج
            // account_type ENUM: revenue, expense, asset, liability, equity
            $counterAccount = 'revenue';
            $counterParty = $staff;
            if ($party && !($options['skip_party_balance'] ?? false)) {
                // receivable → asset (ذمة العميل هي أصل للشركة)
                // payable    → liability (التزام تجاه المورد)
                $counterAccount = (isset($relationType) && $relationType === 'payable') ? 'liability' : 'asset';
                $counterParty = $party;
            } elseif ($direction !== 'in') {
                $counterAccount = 'expense';
            }
            
            $ledgerService->recordEntry(
                $counterParty,
                $counterAccount,
                $amount,
                $direction === 'in' ? 'credit' : 'debit',
                $options['description'] ?? ($direction === 'in' ? 'تسوية ذمة/إيراد' : 'تسوية ذمة/مصروف'),
                now(),
                $operationId
            );

            return $operationId;
        });
    }

    /**
     * إلغاء وعكس الأثر المالي لفاتورة بالكامل.
     */
    public function reverseInvoice(Invoice $invoice, array $options = []): void
    {
        $engine = app(FinancialEngine::class);
        $reason = $options['reason'] ?? 'إلغاء فاتورة';

        // 1. Reverse all related payments first to prevent orphaned cash operations
        $payments = \App\Models\InvoicePayment::withoutGlobalScopes()->where('invoice_id', $invoice->id)->get();
        foreach ($payments as $payment) {
            if ($payment->financial_operation_id) {
                try {
                    $engine->reverseOperation($payment->financial_operation_id, $reason);
                } catch (\Exception $e) {
                    // Safely ignore if already reversed
                }
            }
        }

        // 2. Reverse the main invoice operation(s)
        $operations = \App\Models\FinancialOperation::withoutGlobalScopes()
            ->where('source_type', get_class($invoice))
            ->where('source_id', $invoice->id)
            ->get();

        foreach ($operations as $operation) {
            try {
                $engine->reverseOperation($operation->id, $reason);
            } catch (\Exception $e) {
                // Safely ignore if already reversed
            }
        }
    }

    /**
     * تحصيل دفعات يدوية من العميل — يُعيد operationId لربطه بـ Payment.
     */
    public function collectPayment(User $staff, User $party, float $amount, array $options = []): string
    {
        $companyId = $options['company_id'] ?? $staff->active_company_id ?? $party->active_company_id;
        return $this->recordPayment($companyId, $staff, $party, $amount, 'in', $options);
    }
}

