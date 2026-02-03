<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceType;
use App\Services\DocumentServiceInterface;
use App\Services\Traits\InvoiceHelperTrait;
use Illuminate\Support\Facades\Log;

class ReturnService implements DocumentServiceInterface
{
    use InvoiceHelperTrait;

    /**
     * إنشاء فاتورة مرتجع (بيع أو شراء).
     */
    public function create(array $data): Invoice
    {
        try {
            Log::info('ReturnService: بدء إنشاء فاتورة مرتجع.', ['data' => $data]);

            // 1. حساب الإجماليات
            $calculator = app(\App\Services\InvoiceCalculator::class);
            $calculatedData = $calculator->calculateTotals($data['items'], $data);
            $data = array_merge($data, $calculatedData);

            // 2. إنشاء الفاتورة
            $invoice = $this->createInvoice($data);

            // 3. إنشاء البنود
            $this->createInvoiceItems($invoice, $data['items'], $data['company_id'] ?? null, $data['created_by'] ?? null);

            // 4. معالجة المخزون بناءً على نوع المرتجع
            if ($invoice->invoice_type_code === 'sale_return') {
                // مرتجع مبيعات: زيادة المخزون
                $this->incrementStockForItems(
                    $data['items'],
                    $data['company_id'] ?? null,
                    $data['created_by'] ?? null,
                    $data['warehouse_id'] ?? null
                );
            } elseif ($invoice->invoice_type_code === 'purchase_return') {
                // مرتجع مشتريات: خصم من المخزون
                $this->deductStockForItems($data['items'], $data['warehouse_id'] ?? null);
            }

            // 5. تسجيل النشاط
            $invoice->logCreated('إنشاء فاتورة مرتجع رقم ' . $invoice->invoice_number);

            return $invoice;
        } catch (\Throwable $e) {
            Log::error('ReturnService: فشل إنشاء الفاتورة.', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * تحديث فاتورة مرتجع.
     */
    public function update(array $data, Invoice $invoice): Invoice
    {
        try {
            Log::info('ReturnService: بدء تحديث فاتورة مرتجع.', ['invoice_id' => $invoice->id]);

            // التحقق من إمكانية التعديل (اختياري)

            // عكس الحركات القديمة
            if ($invoice->invoice_type_code === 'sale_return') {
                $this->decrementStockForInvoiceItems($invoice);
            } elseif ($invoice->invoice_type_code === 'purchase_return') {
                $this->returnStockForItems($invoice);
            }

            // تحديث البيانات
            $calculator = app(\App\Services\InvoiceCalculator::class);
            $calculatedData = $calculator->calculateTotals($data['items'], $data);
            $data = array_merge($data, $calculatedData);

            $this->updateInvoice($invoice, $data);
            $this->syncInvoiceItems($invoice, $data['items'], $data['company_id'] ?? null, $data['updated_by'] ?? null);

            // تطبيق الحركات الجديدة
            if ($invoice->invoice_type_code === 'sale_return') {
                $this->incrementStockForItems(
                    $data['items'],
                    $data['company_id'] ?? null,
                    $data['updated_by'] ?? null,
                    $data['warehouse_id'] ?? null
                );
            } elseif ($invoice->invoice_type_code === 'purchase_return') {
                $this->deductStockForItems($data['items'], $data['warehouse_id'] ?? null);
            }

            return $invoice;
        } catch (\Throwable $e) {
            Log::error('ReturnService: فشل تحديث الفاتورة.', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * إلغاء فاتورة مرتجع.
     */
    public function cancel(Invoice $invoice): Invoice
    {
        try {
            Log::info('ReturnService: بدء إلغاء فاتورة مرتجع.', ['invoice_id' => $invoice->id]);

            // عكس حركات المخزون
            if ($invoice->invoice_type_code === 'sale_return') {
                $this->decrementStockForInvoiceItems($invoice);
            } elseif ($invoice->invoice_type_code === 'purchase_return') {
                $this->returnStockForItems($invoice);
            }

            $invoice->update(['status' => 'canceled']);
            $this->deleteInvoiceItems($invoice);

            return $invoice;
        } catch (\Throwable $e) {
            Log::error('ReturnService: فشل إلغاء الفاتورة.', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
