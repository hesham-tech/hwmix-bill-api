<?php

namespace App\Services;

use App\Models\User;
use App\Models\Invoice;
use Illuminate\Support\Facades\Auth;
use App\Services\DocumentServiceInterface;
use App\Services\Traits\InvoiceHelperTrait;
use App\Services\InstallmentService;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

class InstallmentSaleInvoiceService implements DocumentServiceInterface
{
    use InvoiceHelperTrait;

    protected AccountingService $accounting;

    public function __construct(AccountingService $accounting)
    {
        $this->accounting = $accounting;
    }

    /**
     * إنشاء فاتورة بيع بالتقسيط جديدة.
     *
     * @param array $data بيانات الفاتورة.
     * @return Invoice الفاتورة التي تم إنشاؤها.
     * @throws \Throwable
     */
    public function create(array $data): Invoice
    {
        try {
            // التحقق من توافر المنتجات في المخزون
            $this->checkVariantsStock($data['items'], 'deduct', $data['warehouse_id'] ?? null);

            // إنشاء الفاتورة الرئيسية
            $invoice = $this->createInvoice($data);
            if (!$invoice || !$invoice->id) {
                throw new \Exception('فشل في إنشاء الفاتورة.');
            }

            // إنشاء بنود الفاتورة
            $this->createInvoiceItems($invoice, $data['items'], $data['company_id'] ?? null, $data['created_by'] ?? null);

            // خصم الكمية من المخزون
            $this->deductStockForItems($data['items'], $data['warehouse_id'] ?? null);

            $authUser = Auth::user();
            $cashBoxId = $data['cash_box_id'] ?? null;
            $userCashBoxId = $data['user_cash_box_id'] ?? null;

            // ✅ تسجيل الأثر المالي الموحد لفاتورة التقسيط
            // سيقوم AccountingService بتسجيل مديونية كامل المبلغ، ثم تسجيل الدفعة المقدمة كتحصيل
            $this->accounting->recordInvoiceCreation($invoice, [
                'cash_box_id' => $cashBoxId,
                'user_cash_box_id' => $userCashBoxId
            ]);

            // إنشاء خطة الأقساط
            if (isset($data['installment_plan'])) {
                app(InstallmentService::class)->createInstallments($data, $invoice->id);
            }

            // تسجيل عملية الإنشاء

            return $invoice;
        } catch (\Throwable $e) {
            Log::error('InstallmentSaleInvoiceService: فشل في إنشاء فاتورة بيع بالتقسيط.', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

    /**
     * تحديث فاتورة بيع بالتقسيط موجودة.
     *
     * @param array $data البيانات الجديدة للفاتورة.
     * @param Invoice $invoice الفاتورة المراد تحديثها.
     * @return Invoice الفاتورة المحدثة.
     * @throws \Throwable
     */
    public function update(array $data, Invoice $invoice): Invoice
    {
        try {
            // إلغاء الفاتورة القديمة أولاً (يعكس جميع التأثيرات المالية والمخزنية)
            // ملاحظة: دالة cancel ستحدث حالة الفاتورة القديمة إلى 'canceled'
            $this->cancel($invoice);

            // إعادة إنشاء فاتورة جديدة بالبيانات المحدثة
            $newInvoice = $this->create($data);

            // تسجيل عملية التحديث للفاتورة الجديدة

            return $newInvoice;
        } catch (\Throwable $e) {
            Log::error('InstallmentSaleInvoiceService: فشل في تحديث فاتورة بيع بالتقسيط.', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

    /**
     * إلغاء فاتورة بيع بالتقسيط.
     *
     * @param Invoice $invoice الفاتورة المراد إلغاؤها.
     * @return Invoice الفاتورة الملغاة.
     * @throws \Exception إذا كانت الفاتورة مدفوعة بالكامل أو بها أقساط مدفوعة.
     * @throws \Throwable
     */
    public function cancel(Invoice $invoice): Invoice
    {
        try {
            // ✅ 3. عكس الأثر المالي بالكامل عبر AccountingService
            // سيعالج عكس مديونية الفاتورة كاملة + رد جميع المبالغ المحصلة (دفعة مقدمة + أقساط مسددة)
            $this->accounting->reverseInvoice($invoice, [
                'cash_box_id' => $invoice->cash_box_id,
                'user_cash_box_id' => $invoice->user_cash_box_id
            ]);


            // تغيير حالة الفاتورة إلى ملغاة
            $invoice->update(['status' => 'canceled']);

            // حذف بنود الفاتورة (اختياري ولكن شائع بعد الإلغاء)
            $this->deleteInvoiceItems($invoice);

            // تسجيل عملية الإلغاء

            return $invoice;
        } catch (\Throwable $e) {
            Log::error('InstallmentSaleInvoiceService: فشل في إلغاء فاتورة بيع بالتقسيط.', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }
}
