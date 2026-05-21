<?php

namespace Modules\Sales\Services;

use Modules\Sales\Models\Invoice;
use Modules\Inventory\Models\ProductVariant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Modules\Accounting\Services\AccountingService;
use Modules\Core\Services\DocumentServiceInterface;
use Modules\Sales\Services\Traits\InvoiceHelperTrait;
use App\Services\InvoiceCalculator;
use Illuminate\Validation\ValidationException;

class PurchaseInvoiceService implements DocumentServiceInterface
{
    use InvoiceHelperTrait;

    protected AccountingService $accounting;

    public function __construct(AccountingService $accounting)
    {
        $this->accounting = $accounting;
    }

    public function create(array $data): Invoice
    {
        // --- فحوصات مسبقة قبل البدء ---
        if (empty($data['company_id'])) {
            throw new \Exception('فشل إنشاء فاتورة الشراء: لا توجد شركة نشطة مختارة. يرجى تسجيل الخروج وإعادة الدخول واختيار الشركة.');
        }

        if (empty($data['items'])) {
            throw new \Exception('فشل إنشاء فاتورة الشراء: لا توجد منتجات في الفاتورة.');
        }

        try {
            $calculator = app(InvoiceCalculator::class);
            $data = array_merge($data, $calculator->calculateTotals($data['items'], $data));

            foreach ($data['items'] as $index => $item) {
                if (!ProductVariant::find($item['variant_id'])) {
                    throw ValidationException::withMessages([
                        "items.$index.variant_id" => ["المتغير بمعرف {$item['variant_id']} غير موجود."]
                    ]);
                }
            }

            // الخطوة 1: إنشاء الفاتورة
            try {
                $invoice = $this->createInvoice($data);
                if (!$invoice || !$invoice->id) {
                    throw new \Exception('فشل في إنشاء سجل الفاتورة في قاعدة البيانات.');
                }
            } catch (\Throwable $e) {
                throw new \Exception('فشل إنشاء الفاتورة: ' . $e->getMessage(), 0, $e);
            }

            // الخطوة 2: إضافة عناصر الفاتورة
            try {
                $this->createInvoiceItems($invoice, $data['items'], $data['company_id'] ?? null, $data['created_by'] ?? null);
            } catch (\Throwable $e) {
                throw new \Exception('فشل إضافة عناصر الفاتورة: ' . $e->getMessage(), 0, $e);
            }

            // الخطوة 3: زيادة المخزون
            try {
                $this->incrementStockForItems($data['items'], $data['company_id'] ?? null, $data['created_by'] ?? null, $data['warehouse_id'] ?? null);
            } catch (\Throwable $e) {
                throw new \Exception('فشل تحديث المخزون: ' . $e->getMessage(), 0, $e);
            }

            // الخطوة 4: تسجيل الأثر المحاسبي
            try {
                $this->accounting->recordInvoiceCreation($invoice, [
                    'cash_box_id'      => $data['cash_box_id'] ?? null,
                    'user_cash_box_id' => $data['user_cash_box_id'] ?? null,
                ]);
            } catch (\Throwable $e) {
                throw new \Exception('فشل تسجيل الأثر المالي للفاتورة: ' . $e->getMessage(), 0, $e);
            }

            return $invoice;
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('PurchaseInvoiceService: فشل في إنشاء فاتورة الشراء.', [
                'error'      => $e->getMessage(),
                'company_id' => $data['company_id'] ?? null,
                'created_by' => $data['created_by'] ?? null,
            ]);
            throw $e;
        }
    }


    public function update(array $data, Invoice $invoice): Invoice
    {
        try {
            $calculator = app(InvoiceCalculator::class);
            $data = array_merge($data, $calculator->calculateTotals($data['items'], $data));

            $freshInvoice = Invoice::find($invoice->id);
            $this->accounting->reverseInvoice($freshInvoice, [
                'cash_box_id' => $freshInvoice->cash_box_id,
                'user_cash_box_id' => $freshInvoice->user_cash_box_id
            ]);

            $this->decrementStockForInvoiceItems($invoice);
            $this->updateInvoice($invoice, $data);

            $this->accounting->recordInvoiceCreation($invoice, [
                'cash_box_id' => $data['cash_box_id'] ?? null,
                'user_cash_box_id' => $data['user_cash_box_id'] ?? null
            ]);

            foreach ($data['items'] as $item) {
                if (!ProductVariant::find($item['variant_id'])) {
                    throw ValidationException::withMessages(['variant_id' => ["المتغير بمعرف {$item['variant_id']} غير موجود."]]);
                }
            }

            $this->syncInvoiceItems($invoice, $data['items'], $data['company_id'] ?? null, $data['updated_by'] ?? null);
            $this->incrementStockForItems($data['items'], $data['company_id'] ?? null, $data['updated_by'] ?? null, $data['warehouse_id'] ?? null);

            return $invoice;
        } catch (\Throwable $e) {
            Log::error('PurchaseInvoiceService: فشل في تحديث فاتورة الشراء.', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function cancel(Invoice $invoice): Invoice
    {
        try {
            if ($invoice->status === 'paid') throw new \Exception('لا يمكن إلغاء فاتورة مدفوعة بالكامل.');

            $this->decrementStockForInvoiceItems($invoice);
            $invoice->update(['status' => 'canceled']);
            $this->deleteInvoiceItems($invoice);

            $this->accounting->reverseInvoice($invoice, [
                'cash_box_id' => $invoice->cash_box_id,
                'user_cash_box_id' => $invoice->user_cash_box_id
            ]);

            return $invoice;
        } catch (\Throwable $e) {
            Log::error('PurchaseInvoiceService: فشل في إلغاء فاتورة الشراء.', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
