<?php

namespace App\Services;

use App\Models\User;
use App\Models\Invoice;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Services\AccountingService;
use App\Services\Traits\InvoiceHelperTrait;
use Illuminate\Validation\ValidationException;

class PurchaseInvoiceService implements DocumentServiceInterface
{
    use InvoiceHelperTrait;

    protected AccountingService $accounting;

    public function __construct(AccountingService $accounting)
    {
        $this->accounting = $accounting;
    }

    /**
     * إنشاء فاتورة شراء جديدة.
     *
     * @param array $data بيانات الفاتورة.
     * @return Invoice الفاتورة التي تم إنشاؤها.
     * @throws \Throwable
     */
    public function create(array $data): Invoice
    {
        try {
            Log::info('PurchaseInvoiceService: بدء إنشاء فاتورة شراء.', ['data' => $data]);

            // ✅ استخدام InvoiceCalculator لحساب الإجماليات
            $calculator = app(\App\Services\InvoiceCalculator::class);
            $calculatedData = $calculator->calculateTotals($data['items'], $data);

            // دمج البيانات المحسوبة
            $data = array_merge($data, $calculatedData);

            // التحقق من المنتجات والمتغيرات
            foreach ($data['items'] as $item) {
                $variant = ProductVariant::find($item['variant_id']);
                if (!$variant) {
                    throw ValidationException::withMessages([
                        'variant_id' => ['المتغير بمعرف ' . $item['variant_id'] . ' غير موجود.'],
                    ]);
                }
            }

            // إنشاء الفاتورة الرئيسية
            $invoice = $this->createInvoice($data);
            if (!$invoice || !$invoice->id) {
                throw new \Exception('فشل في إنشاء الفاتورة.');
            }

            // إنشاء بنود الفاتورة
            $this->createInvoiceItems($invoice, $data['items'], $data['company_id'] ?? null, $data['created_by'] ?? null);

            // زيادة الكمية في المخزون للبنود المشتراة
            $this->incrementStockForItems(
                $data['items'],
                $data['company_id'] ?? null,
                $data['created_by'] ?? null,
                $data['warehouse_id'] ?? null
            );

            $authUser = Auth::user();
            $cashBoxId = $data['cash_box_id'] ?? null;
            $userCashBoxId = $data['user_cash_box_id'] ?? null;

            // ✅ تسجيل الأثر المالي الموحد لفاتورة الشراء
            $this->accounting->recordInvoiceCreation($invoice, [
                'cash_box_id' => $cashBoxId,
                'user_cash_box_id' => $userCashBoxId
            ]);

            // تسجيل عملية الإنشاء

            return $invoice;
        } catch (\Throwable $e) {
            Log::error('PurchaseInvoiceService: فشل في إنشاء فاتورة الشراء.', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

    /**
     * تحديث فاتورة شراء موجودة.
     *
     * @param array $data البيانات الجديدة للفاتورة.
     * @param Invoice $invoice الفاتورة المراد تحديثها.
     * @return Invoice الفاتورة المحدثة.
     * @throws \Throwable
     */
    public function update(array $data, Invoice $invoice): Invoice
    {
        try {
            Log::info('PurchaseInvoiceService: بدء تحديث فاتورة شراء.', ['invoice_id' => $invoice->id, 'data' => $data]);

            // ✅ استخدام InvoiceCalculator لحساب الإجماليات
            $calculator = app(\App\Services\InvoiceCalculator::class);
            $calculatedData = $calculator->calculateTotals($data['items'], $data);

            // دمج البيانات المحسوبة
            $data = array_merge($data, $calculatedData);

            // جلب القيم الأصلية للفاتورة من DB قبل أي تعديل
            $freshInvoice = Invoice::find($invoice->id);
            if (!$freshInvoice) {
                throw new \Exception("فشل العثور على الفاتورة (ID: {$invoice->id}) أثناء التحديث.");
            }
            $oldPaidAmount = $freshInvoice->paid_amount;
            $oldRemainingAmount = $freshInvoice->remaining_amount;

            $authUser = Auth::user();
            $cashBoxId = $data['cash_box_id'] ?? null;
            $userCashBoxId = $data['user_cash_box_id'] ?? null;

            // ✅ عكس الأثر المالي القديم قبل التحديث
            $this->accounting->reverseInvoice($freshInvoice, [
                'cash_box_id' => $freshInvoice->cash_box_id,
                'user_cash_box_id' => $freshInvoice->user_cash_box_id
            ]);

            // خصم المخزون للعناصر القديمة (عكس عملية الشراء الأصلية)
            $this->decrementStockForInvoiceItems($invoice);

            // تحديث بيانات الفاتورة الرئيسية
            $this->updateInvoice($invoice, $data);

            // ✅ تسجيل الأثر المالي الجديد للفاتورة المحدثة
            $this->accounting->recordInvoiceCreation($invoice, [
                'cash_box_id' => $cashBoxId,
                'user_cash_box_id' => $userCashBoxId
            ]);

            // التحقق من المنتجات والمتغيرات الجديدة
            foreach ($data['items'] as $item) {
                $variant = ProductVariant::find($item['variant_id']);
                if (!$variant) {
                    throw ValidationException::withMessages([
                        'variant_id' => ['المتغير بمعرف ' . $item['variant_id'] . ' غير موجود.'],
                    ]);
                }
            }

            // مزامنة بنود الفاتورة
            $this->syncInvoiceItems($invoice, $data['items'], $data['company_id'] ?? null, $data['updated_by'] ?? null);

            // زيادة المخزون للبنود الجديدة/المحدثة
            $this->incrementStockForItems(
                $data['items'],
                $data['company_id'] ?? null,
                $data['updated_by'] ?? null,
                $data['warehouse_id'] ?? null
            );

            // تسجيل عملية التحديث

            return $invoice;
        } catch (\Throwable $e) {
            Log::error('PurchaseInvoiceService: فشل في تحديث فاتورة الشراء.', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

    /**
     * إلغاء فاتورة شراء.
     *
     * @param Invoice $invoice الفاتورة المراد إلغاؤها.
     * @return Invoice الفاتورة الملغاة.
     * @throws \Exception إذا كانت الفاتورة مدفوعة بالكامل.
     * @throws \Throwable
     */
    public function cancel(Invoice $invoice): Invoice
    {
        try {
            if ($invoice->status === 'paid') {
                throw new \Exception('لا يمكن إلغاء فاتورة مدفوعة بالكامل.');
            }

            // خصم الكمية من المخزون (عكس عملية الشراء)
            $this->decrementStockForInvoiceItems($invoice);

            // تغيير حالة الفاتورة
            $invoice->update(['status' => 'canceled']);

            // حذف البنود
            $this->deleteInvoiceItems($invoice);

            // عكس الأثر المالي للفاتورة الملغاة
            $this->accounting->reverseInvoice($invoice, [
                'cash_box_id' => $invoice->cash_box_id,
                'user_cash_box_id' => $invoice->user_cash_box_id
            ]);


            return $invoice;
        } catch (\Throwable $e) {
            Log::error('PurchaseInvoiceService: فشل في إلغاء فاتورة الشراء.', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }
}
