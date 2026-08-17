<?php

namespace App\Services;

use App\Contracts\FinancialEngineInterface;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use App\Models\InvoicePayment;
use App\Models\Expense;
use App\Models\Revenue;
use App\Models\CashReconciliation;
use App\Models\FinancialOperation;
use App\Models\Transaction;
use App\Models\FinancialLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Exception;

/**
 * المحرك المالي للنظام (FinancialEngine Orchestrator) - النواة المالية الموحدة لإتمام الحركات والقيود.
 */
class FinancialEngine implements FinancialEngineInterface
{
    protected FinancialOperationService $operationService;
    protected CashService $cashService;
    protected ReceivableService $receivableService;
    protected PayableService $payableService;
    protected FinancialLedgerService $ledgerService;
    protected InvoiceStateService $invoiceStateService;
    protected CustomerCreditService $creditService;
    protected CashBoxDomainRules $rules;

    public function __construct(
        FinancialOperationService $operationService,
        CashService $cashService,
        ReceivableService $receivableService,
        PayableService $payableService,
        FinancialLedgerService $ledgerService,
        InvoiceStateService $invoiceStateService,
        CustomerCreditService $creditService,
        CashBoxDomainRules $rules
    ) {
        $this->operationService = $operationService;
        $this->cashService = $cashService;
        $this->receivableService = $receivableService;
        $this->payableService = $payableService;
        $this->ledgerService = $ledgerService;
        $this->invoiceStateService = $invoiceStateService;
        $this->creditService = $creditService;
        $this->rules = $rules;
    }

    /**
     * استلام نقدية وإيداعها في الخزينة (مع حماية Idempotency)
     */
    public function receiveMoney(float $amount, int $cashBoxId, string $operationId, array $metadata = []): void
    {
        $exists = Transaction::withoutGlobalScopes()
            ->where('financial_operation_id', $operationId)
            ->where('cashbox_id', $cashBoxId)
            ->where('type', 'deposit')
            ->exists();
            
        if ($exists) {
            return;
        }

        $cashBox = \Modules\Accounting\Models\CashBox::withoutGlobalScopes()->findOrFail($cashBoxId);
        $this->rules->validateOperation($cashBox, $amount, 'deposit', $cashBox->company_id);

        $this->cashService->deposit($amount, $cashBoxId, $operationId, $metadata);
    }

    /**
     * صرف نقدية من الخزينة (مع حماية Idempotency)
     */
    public function payMoney(float $amount, int $cashBoxId, string $operationId, array $metadata = []): void
    {
        $exists = Transaction::withoutGlobalScopes()
            ->where('financial_operation_id', $operationId)
            ->where('cashbox_id', $cashBoxId)
            ->where('type', 'withdraw')
            ->exists();

        if ($exists) {
            return;
        }

        $cashBox = \Modules\Accounting\Models\CashBox::withoutGlobalScopes()->findOrFail($cashBoxId);
        $this->rules->validateOperation($cashBox, $amount, 'withdraw', $cashBox->company_id);

        $this->cashService->withdraw($amount, $cashBoxId, $operationId, $metadata);
    }

    /**
     * إثبات مديونية مستحقة على عميل (ذمة مدينة)
     */
    public function createReceivable(User $customer, float $amount, string $operationId, array $metadata = []): void
    {
        $exists = Transaction::withoutGlobalScopes()
            ->where('financial_operation_id', $operationId)
            ->where('user_id', $customer->id)
            ->whereNull('cashbox_id')
            ->where('type', 'deposit')
            ->exists();

        if ($exists) {
            return;
        }

        $this->receivableService->add($customer, $amount, $operationId, $metadata);
    }

    /**
     * تخفيض مديونية عميل (تحصيل ديون)
     */
    public function reduceReceivable(User $customer, float $amount, string $operationId, array $metadata = []): void
    {
        $exists = Transaction::withoutGlobalScopes()
            ->where('financial_operation_id', $operationId)
            ->where('user_id', $customer->id)
            ->whereNull('cashbox_id')
            ->where('type', 'withdraw')
            ->exists();

        if ($exists) {
            return;
        }

        $this->receivableService->reduce($customer, $amount, $operationId, $metadata);
    }

    /**
     * إثبات التزام مستحق لمورد (ذمة دائنة)
     */
    public function createPayable(User $supplier, float $amount, string $operationId, array $metadata = []): void
    {
        $exists = Transaction::withoutGlobalScopes()
            ->where('financial_operation_id', $operationId)
            ->where('user_id', $supplier->id)
            ->whereNull('cashbox_id')
            ->where('type', 'deposit')
            ->exists();

        if ($exists) {
            return;
        }

        $this->payableService->add($supplier, $amount, $operationId, $metadata);
    }

    /**
     * تخفيض التزام لمورد (سداد التزام)
     */
    public function reducePayable(User $supplier, float $amount, string $operationId, array $metadata = []): void
    {
        $exists = Transaction::withoutGlobalScopes()
            ->where('financial_operation_id', $operationId)
            ->where('user_id', $supplier->id)
            ->whereNull('cashbox_id')
            ->where('type', 'withdraw')
            ->exists();

        if ($exists) {
            return;
        }

        $this->payableService->reduce($supplier, $amount, $operationId, $metadata);
    }

    public function transferCash(int $fromBoxId, int $toBoxId, float $amount, string $operationId, ?string $desc = null): void
    {
        $exists = FinancialOperation::withoutGlobalScopes()
            ->where('id', $operationId)
            ->exists();

        if ($exists) {
            return;
        }

        $fromBox = \Modules\Accounting\Models\CashBox::withoutGlobalScopes()->findOrFail($fromBoxId);
        $toBox = \Modules\Accounting\Models\CashBox::withoutGlobalScopes()->findOrFail($toBoxId);

        $this->rules->validateOperation($fromBox, $amount, 'withdraw', $fromBox->company_id);
        $this->rules->validateOperation($toBox, $amount, 'deposit', $toBox->company_id);

        DB::transaction(function () use ($fromBoxId, $toBoxId, $amount, $operationId, $desc, $fromBox, $toBox) {
            $this->operationService->createOperation([
                'id' => $operationId,
                'company_id' => $fromBox->company_id,
                'type' => 'transfer_cash',
                'amount' => $amount,
                'source_type' => \Modules\Accounting\Models\CashBox::class,
                'source_id' => $fromBoxId, // يمكننا استخدام مصدر التحويل
                'metadata' => [
                    'from_cashbox_id' => $fromBoxId,
                    'to_cashbox_id' => $toBoxId,
                    'description' => $desc,
                ],
            ]);

            $this->cashService->transfer($fromBoxId, $toBoxId, $amount, $operationId, $desc);

            // ترحيل قيد التحويل الداخلي في دفتر الأستاذ دون التأثير على المصروفات أو الإيرادات
            $this->ledgerService->recordEntry(
                $fromBox,
                'asset',
                $amount,
                'debit',
                "تحويل مالي وارد إلى الخزينة: {$toBox->name}",
                now(),
                $operationId
            );

            $this->ledgerService->recordEntry(
                $fromBox,
                'asset',
                $amount,
                'credit',
                "تحويل مالي صادر من الخزينة: {$fromBox->name}",
                now(),
                $operationId
            );
        });
    }

    /**
     * عكس عملية مالية بالكامل باستخدام معرفها (العمليات العكسية الموثقة)
     */
    public function reverseOperation(string $originalOperationId, ?string $reason = null): string
    {
        return DB::transaction(function () use ($originalOperationId, $reason) {
            $originalOp = FinancialOperation::withoutGlobalScopes()->findOrFail($originalOperationId);
            
            // Invariant: لا يمكن إلغاء عملية ملغاة مسبقاً
            if ($originalOp->status === 'reversed') {
                throw new Exception("العملية المالية ملغاة مسبقاً ولا يمكن تكرار إلغائها.");
            }

            $reversalOpId = (string) Str::uuid();
            $reversalType = "{$originalOp->type}_reversal";

            // 1. إنشاء عملية إلغاء محاسبية جديدة
            $reversalOp = $this->operationService->createOperation([
                'id' => $reversalOpId,
                'company_id' => $originalOp->company_id,
                'type' => $reversalType,
                'amount' => $originalOp->amount,
                'source_type' => $originalOp->source_type,
                'source_id' => $originalOp->source_id,
                'metadata' => [
                    'reversed_operation_id' => $originalOperationId,
                    'reason' => $reason ?? 'إلغاء المعاملة المالية الأصلية',
                ],
            ]);

            // 2. تتبع وعكس قيود الأستاذ العام
            $ledgers = FinancialLedger::withoutGlobalScopes()->where('financial_operation_id', $originalOperationId)->get();
            foreach ($ledgers as $ledger) {
                // عكس المدين والدائن
                $oppositeType = $ledger->type === 'debit' ? 'credit' : 'debit';
                $this->ledgerService->recordEntry(
                    $ledger->source,
                    $ledger->account_type,
                    (float)$ledger->amount,
                    $oppositeType,
                    "عكس قيد: {$ledger->description}",
                    now(),
                    $reversalOpId
                );
            }

            // 3. تتبع وعكس الحركات المالية بالخزن والذمم
            $txs = Transaction::withoutGlobalScopes()->where('financial_operation_id', $originalOperationId)->get();
            foreach ($txs as $tx) {
                if ($tx->cashbox_id) {
                    // حركة خزنة
                    if (in_array($tx->type, ['deposit', 'transfer_in', 'إيداع', 'تحويل_وارد', 'تحويل_مستلم'])) {
                        // عكس الإيداع أو التحويل المستلم بعملية سحب
                        $this->cashService->withdraw((float)$tx->amount, $tx->cashbox_id, $reversalOpId, [
                            'description' => "عكس إيداع لعملية ملغاة رقم {$originalOperationId}"
                        ]);
                    } else {
                        // عكس السحب أو التحويل الصادر بعملية إيداع
                        $this->cashService->deposit((float)$tx->amount, $tx->cashbox_id, $reversalOpId, [
                            'description' => "عكس صرف لعملية ملغاة رقم {$originalOperationId}"
                        ]);
                    }
                } else if ($tx->user_id) {
                    // حركة ذمة (عميل أو مورد)
                    $user = User::withoutGlobalScopes()->find($tx->user_id);
                    if (!$user) {
                        continue;
                    }
                    $balanceModel = \Modules\Companies\Models\StakeholderFinancialBalance::where([
                        'company_id' => $tx->company_id,
                        'user_id' => $tx->user_id,
                    ])->first();

                    if ($balanceModel && $balanceModel->relation_type === 'receivable') {
                        if ($tx->type === 'deposit') {
                            $this->receivableService->reduce($user, (float)$tx->amount, $reversalOpId, [
                                'company_id' => $tx->company_id,
                                'allow_negative' => true,
                                'description' => "عكس مديونية لعملية ملغاة رقم {$originalOperationId}"
                            ]);
                        } else {
                            $this->receivableService->add($user, (float)$tx->amount, $reversalOpId, [
                                'company_id' => $tx->company_id,
                                'description' => "إعادة مديونية لعملية ملغاة رقم {$originalOperationId}"
                            ]);
                        }
                    } elseif ($balanceModel && $balanceModel->relation_type === 'payable') {
                        if ($tx->type === 'deposit') {
                            $this->payableService->reduce($user, (float)$tx->amount, $reversalOpId, [
                                'company_id' => $tx->company_id,
                                'allow_negative' => true,
                                'description' => "عكس التزام لعملية ملغاة رقم {$originalOperationId}"
                            ]);
                        } else {
                            $this->payableService->add($user, (float)$tx->amount, $reversalOpId, [
                                'company_id' => $tx->company_id,
                                'description' => "إعادة التزام لعملية ملغاة رقم {$originalOperationId}"
                            ]);
                        }
                    }
                }
            }

            // 4. عكس وتحديث الفواتير ودفعاتها إذا وجدت
            $payments = InvoicePayment::withoutGlobalScopes()->where('financial_operation_id', $originalOperationId)->get();
            foreach ($payments as $payment) {
                $invoice = \App\Models\Invoice::withoutGlobalScopes()->findOrFail($payment->invoice_id);
                // تصفير دفعات الفاتورة التابعة للعملية وإعادة الحساب
                $this->invoiceStateService->cancelPayments($invoice, $originalOperationId);
                $payment->delete(); // إلغاء فيزيائي ناعم للدفعات لعدم تكرارها بالجمع محاسبياً
            }

            // 5. تحديث حالة العملية الأصلية
            $originalOp->status = 'reversed';
            $originalOp->save();

            return $reversalOpId;
        });
    }

    /**
     * ترحيل معالجة الفاتورة وصافي الأثر المالي لها (سند البيع والشراء والخدمات)
     */
    public function processInvoiceCreation(Model $invoice, array $payload): string
    {
        $operationId = $payload['operation_id'] ?? (string) Str::uuid();

        if (FinancialOperation::withoutGlobalScopes()->where('id', $operationId)->exists()) {
            return $operationId;
        }

        return DB::transaction(function () use ($invoice, $payload, $operationId) {
            $type = $invoice->invoice_type_code;
            $netAmount = (float)$invoice->net_amount;
            $paidAmount = (float)$invoice->paid_amount;
            $companyId = $invoice->company_id;

            $cashBoxId = $payload['cash_box_id'] ?? null;
            if ($cashBoxId === null) {
                $creator = User::withoutGlobalScopes()->find($invoice->created_by);
                $cashBoxId = $creator?->getDefaultCashBoxForCompany($companyId)?->id;

                if ($cashBoxId === null) {
                    throw new \Exception("لم يتم تحديد خزينة صالحة للموظف منشئ الفاتورة لإتمام العملية المالية.");
                }
            }

            $userCashBoxId = $payload['user_cash_box_id'] ?? null;

            $party = User::withoutGlobalScopes()->find($invoice->user_id);
            $isCashCustomer = $party && $party->isDefaultCashCustomer($companyId);

            // 1. إنشاء العملية المالية
            $this->operationService->createOperation([
                'id' => $operationId,
                'company_id' => $companyId,
                'type' => "invoice_{$type}_creation",
                'amount' => $netAmount,
                'source_type' => get_class($invoice),
                'source_id' => $invoice->id,
                'metadata' => $payload,
            ]);

            if (in_array($type, ['sale', 'installment_sale', 'service_invoice'])) {
                // مبيعات / مديونية عملاء
                if ($party && !$isCashCustomer) {
                    $this->createReceivable($party, $netAmount, $operationId, [
                        'company_id' => $companyId,
                        'branch_id' => $invoice->branch_id,
                        'description' => "إثبات مديونية فاتورة مبيعات رقم {$invoice->invoice_number}"
                    ]);
                }

                // تسجيل القيد المزدوج بالأستاذ
                $this->ledgerService->recordSaleInvoice($invoice);

                // ربط القيد المتولد بالعملية المالية
                FinancialLedger::withoutGlobalScopes()->where([
                    'source_type' => get_class($invoice),
                    'source_id' => $invoice->id
                ])->update(['financial_operation_id' => $operationId]);

                if ($paidAmount > 0) {
                    // تحصيل الدفعة المقدمة
                    $this->receiveMoney($paidAmount, $cashBoxId, $operationId, [
                        'company_id' => $companyId,
                        'user_id' => $invoice->created_by,
                        'description' => "دفعة مقدمة من فاتورة رقم {$invoice->invoice_number}"
                    ]);

                    if ($party && !$isCashCustomer) {
                        $this->reduceReceivable($party, $paidAmount, $operationId, [
                            'description' => "سداد جزء من مديونية فاتورة مبيعات رقم {$invoice->invoice_number}"
                        ]);
                    }

                    // حفظ الدفعة وتعديل حالة الفاتورة تلقائياً
                    $this->invoiceStateService->recordPayment($invoice, $paidAmount, $operationId, [
                        'cash_box_id' => $cashBoxId,
                        'description' => "دفعة مقدمة من فاتورة مبيعات رقم {$invoice->invoice_number}"
                    ]);
                }
            } elseif ($type === 'purchase') {
                // مشتريات / التزامات موردين
                if ($party && !$isCashCustomer) {
                    $this->createPayable($party, $netAmount, $operationId, [
                        'company_id' => $companyId,
                        'branch_id' => $invoice->branch_id,
                        'description' => "إثبات التزام فاتورة مشتريات رقم {$invoice->invoice_number}"
                    ]);
                }

                $this->ledgerService->recordPurchaseInvoice($invoice);

                FinancialLedger::withoutGlobalScopes()->where([
                    'source_type' => get_class($invoice),
                    'source_id' => $invoice->id
                ])->update(['financial_operation_id' => $operationId]);

                if ($paidAmount > 0) {
                    $this->payMoney($paidAmount, $cashBoxId, $operationId, [
                        'company_id' => $companyId,
                        'user_id' => $invoice->created_by,
                        'description' => "دفعة مقدمة سداد لفاتورة مشتريات رقم {$invoice->invoice_number}"
                    ]);

                    if ($party && !$isCashCustomer) {
                        $this->reducePayable($party, $paidAmount, $operationId, [
                            'description' => "سداد جزء من التزام فاتورة مشتريات رقم {$invoice->invoice_number}"
                        ]);
                    }

                    $this->invoiceStateService->recordPayment($invoice, $paidAmount, $operationId, [
                        'cash_box_id' => $cashBoxId,
                        'description' => "دفعة سداد لفاتورة مشتريات رقم {$invoice->invoice_number}"
                    ]);
                }
            }

            return $operationId;
        });
    }

    /**
     * ترحيل معالجة دفع الفاتورة بالكامل (استلام دفعة)
     */
    public function processPaymentReceipt(Model $invoice, float $amount, array $payload): string
    {
        $operationId = $payload['operation_id'] ?? (string) Str::uuid();

        if (FinancialOperation::withoutGlobalScopes()->where('id', $operationId)->exists()) {
            return $operationId;
        }

        return DB::transaction(function () use ($invoice, $amount, $payload, $operationId) {
            $companyId = $invoice->company_id;
            $cashBoxId = $payload['cash_box_id'] ?? null;
            if ($cashBoxId === null) {
                $creator = User::withoutGlobalScopes()->find($invoice->created_by);
                $cashBoxId = $creator?->getDefaultCashBoxForCompany($companyId)?->id;

                if ($cashBoxId === null) {
                    throw new \Exception("لم يتم تحديد خزينة صالحة للموظف منشئ السند لإتمام العملية المالية.");
                }
            }
            $party = User::withoutGlobalScopes()->find($invoice->user_id);
            $isCashCustomer = $party && $party->isDefaultCashCustomer($companyId);

            $this->operationService->createOperation([
                'id' => $operationId,
                'company_id' => $companyId,
                'type' => 'payment_receipt',
                'amount' => $amount,
                'source_type' => get_class($invoice),
                'source_id' => $invoice->id,
                'metadata' => $payload,
            ]);

            // 1. شحن الخزينة المستلمة بالمال
            $this->receiveMoney($amount, $cashBoxId, $operationId, [
                'company_id' => $companyId,
                'user_id' => $invoice->created_by,
                'description' => "تحصيل دفعة مالية للفاتورة رقم {$invoice->invoice_number}"
            ]);

            // 2. تقليل ذمة العميل إذا لم يكن نقدياً افتراضياً
            if ($party && !$isCashCustomer) {
                $this->reduceReceivable($party, $amount, $operationId, [
                    'description' => "سداد دفعة من فاتورة رقم {$invoice->invoice_number}"
                ]);
            }

            // 3. توثيق دفعة التحصيل وتعديل حالة الفاتورة تلقائياً
            $this->invoiceStateService->recordPayment($invoice, $amount, $operationId, [
                'cash_box_id' => $cashBoxId,
                'description' => "تحصيل دفعة مالية للفاتورة رقم {$invoice->invoice_number}"
            ]);

            // 4. ترحيل قيود الاستلام بالأستاذ العام
            $this->ledgerService->recordEntry(
                $invoice,
                'asset',
                $amount,
                'debit',
                "استلام دفعة نقدية - فاتورة رقم {$invoice->invoice_number}",
                now(),
                $operationId
            );
            
            $this->ledgerService->recordEntry(
                $invoice,
                'asset',
                $amount,
                'credit',
                "تقليل ذمم العملاء بسداد دفعة - فاتورة رقم {$invoice->invoice_number}",
                now(),
                $operationId
            );

            return $operationId;
        });
    }

    /**
     * ترحيل معالجة تسجيل مصروف بالكامل
     */
    public function processExpenseCreation(Model $expense, array $payload): string
    {
        $operationId = $payload['operation_id'] ?? (string) Str::uuid();

        if (FinancialOperation::withoutGlobalScopes()->where('id', $operationId)->exists()) {
            return $operationId;
        }

        return DB::transaction(function () use ($expense, $payload, $operationId) {
            $amount = (float)$expense->amount;
            $cashBoxId = $expense->cash_box_id;

            $this->operationService->createOperation([
                'id' => $operationId,
                'company_id' => $expense->company_id,
                'type' => 'expense_creation',
                'amount' => $amount,
                'source_type' => get_class($expense),
                'source_id' => $expense->id,
                'metadata' => $payload,
            ]);

            // خصم النقدية من الخزينة المحددة للمصروف
            $this->payMoney($amount, $cashBoxId, $operationId, [
                'description' => "تسجيل وصرف مصروف: {$expense->category?->name}"
            ]);

            // ترحيل القيود الدفترية بالأستاذ العام
            $this->ledgerService->recordEntry(
                $expense,
                'expense',
                $amount,
                'debit',
                "إثبات مصروف: {$expense->category?->name} - {$expense->notes}",
                now(),
                $operationId
            );

            $this->ledgerService->recordEntry(
                $expense,
                'asset',
                $amount,
                'credit',
                "دفع مصروف نقداً من الصندوق",
                now(),
                $operationId
            );

            return $operationId;
        });
    }

    /**
     * ترحيل معالجة تسجيل إيراد بالكامل
     */
    public function processRevenueCreation(Model $revenue, array $payload): string
    {
        $operationId = $payload['operation_id'] ?? (string) Str::uuid();

        if (FinancialOperation::withoutGlobalScopes()->where('id', $operationId)->exists()) {
            return $operationId;
        }

        return DB::transaction(function () use ($revenue, $payload, $operationId) {
            $amount = (float)$revenue->amount;
            $cashBoxId = $revenue->wallet_id;

            $this->operationService->createOperation([
                'id' => $operationId,
                'company_id' => $revenue->company_id,
                'type' => 'revenue_creation',
                'amount' => $amount,
                'source_type' => get_class($revenue),
                'source_id' => $revenue->id,
                'metadata' => $payload,
            ]);

            // إيداع النقدية بالخزينة المحددة للإيراد
            $this->receiveMoney($amount, $cashBoxId, $operationId, [
                'description' => "تسجيل وإيداع إيرادات عشوائية: {$revenue->notes}"
            ]);

            $this->ledgerService->recordEntry(
                $revenue,
                'asset',
                $amount,
                'debit',
                "إيداع إيرادات عشوائية بالصندوق: {$revenue->notes}",
                now(),
                $operationId
            );

            $this->ledgerService->recordEntry(
                $revenue,
                'revenue',
                $amount,
                'credit',
                "إثبات إيرادات عشوائية بالدفتر: {$revenue->notes}",
                now(),
                $operationId
            );

            return $operationId;
        });
    }

    /**
     * ترحيل معالجة اعتماد تسوية جرد الخزنة بالكامل
     */
    public function processReconciliationApproval(Model $reconciliation): string
    {
        $operationId = (string) Str::uuid();

        return DB::transaction(function () use ($reconciliation, $operationId) {
            $difference = (float)$reconciliation->difference;
            $companyId = $reconciliation->company_id;
            $cashBoxId = $reconciliation->cashbox_id;

            $this->operationService->createOperation([
                'id' => $operationId,
                'company_id' => $companyId,
                'type' => 'reconciliation_adjustment',
                'amount' => abs($difference),
                'source_type' => get_class($reconciliation),
                'source_id' => $reconciliation->id,
                'metadata' => [
                    'difference' => $difference,
                    'book_balance' => $reconciliation->book_balance,
                    'physical_balance' => $reconciliation->physical_balance,
                ],
            ]);

            if ($difference > 0) {
                // زيادة النقدية تسوية عجز/زيادة بالخزنة
                $this->receiveMoney($difference, $cashBoxId, $operationId, [
                    'description' => "إيداع فروقات زيادة تسوية جرد نقدية"
                ]);

                // ترحيل قيد تسوية إيراد زيادة جرد بالأستاذ
                $this->ledgerService->recordEntry(
                    $reconciliation,
                    'asset',
                    $difference,
                    'debit',
                    "زيادة جرد نقدية بالخزينة رقم {$cashBoxId}",
                    now(),
                    $operationId
                );

                $this->ledgerService->recordEntry(
                    $reconciliation,
                    'revenue',
                    $difference,
                    'credit',
                    "إيرادات تسويات جرد زيادة",
                    now(),
                    $operationId
                );
            } elseif ($difference < 0) {
                // سحب النقدية تسوية عجز جرد بالخزنة
                $deficit = abs($difference);
                $this->payMoney($deficit, $cashBoxId, $operationId, [
                    'description' => "سحب فروقات عجز تسوية جرد نقدية"
                ]);

                // ترحيل قيد تسوية عجز مصروف بالأستاذ
                $this->ledgerService->recordEntry(
                    $reconciliation,
                    'expense',
                    $deficit,
                    'debit',
                    "تسويات عجز جرد نقدية خزينة رقم {$cashBoxId}",
                    now(),
                    $operationId
                );

                $this->ledgerService->recordEntry(
                    $reconciliation,
                    'asset',
                    $deficit,
                    'credit',
                    "تخفيض عجز نقدية جرد الخزينة رقم {$cashBoxId}",
                    now(),
                    $operationId
                );
            }

            return $operationId;
        });
    }
}
