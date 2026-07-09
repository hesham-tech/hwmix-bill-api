<?php

namespace App\Contracts;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use App\Models\InvoicePayment;
use App\Models\Expense;
use App\Models\Revenue;
use App\Models\CashReconciliation;

/**
 * واجهة المحرك المالي العام للنواة المالية لتوحيد كافة العمليات المحاسبية.
 */
interface FinancialEngineInterface
{
    /**
     * استلام نقدية وإيداعها في الخزينة
     */
    public function receiveMoney(float $amount, int $cashBoxId, string $operationId, array $metadata = []): void;

    /**
     * صرف نقدية من الخزينة
     */
    public function payMoney(float $amount, int $cashBoxId, string $operationId, array $metadata = []): void;

    /**
     * إثبات مديونية مستحقة على عميل (ذمة مدينة)
     */
    public function createReceivable(User $customer, float $amount, string $operationId, array $metadata = []): void;

    /**
     * تخفيض مديونية عميل (تحصيل ديون)
     */
    public function reduceReceivable(User $customer, float $amount, string $operationId, array $metadata = []): void;

    /**
     * إثبات التزام مستحق لمورد (ذمة دائنة)
     */
    public function createPayable(User $supplier, float $amount, string $operationId, array $metadata = []): void;

    /**
     * تخفيض التزام لمورد (سداد التزام)
     */
    public function reducePayable(User $supplier, float $amount, string $operationId, array $metadata = []): void;

    /**
     * تحويل نقدية بين خزنتين مع قفل قاعدة البيانات الحصري
     */
    public function transferCash(int $fromBoxId, int $toBoxId, float $amount, string $operationId, ?string $desc = null): void;

    /**
     * عكس عملية مالية بالكامل باستخدام معرفها
     */
    public function reverseOperation(string $originalOperationId, ?string $reason = null): string;

    /**
     * ترحيل معالجة الفاتورة وصافي الأثر المالي لها
     */
    public function processInvoiceCreation(Model $invoice, array $payload): string;

    /**
     * ترحيل معالجة دفع الفاتورة بالكامل
     */
    public function processPaymentReceipt(Model $invoice, float $amount, array $payload): string;

    /**
     * ترحيل معالجة تسجيل مصروف بالكامل
     */
    public function processExpenseCreation(Expense $expense, array $payload): string;

    /**
     * ترحيل معالجة تسجيل إيراد بالكامل
     */
    public function processRevenueCreation(Revenue $revenue, array $payload): string;

    /**
     * ترحيل معالجة اعتماد تسوية جرد الخزنة بالكامل
     */
    public function processReconciliationApproval(CashReconciliation $reconciliation): string;
}
