<?php

namespace Modules\Sales\Services;

use Modules\Sales\Models\Invoice;
use App\Models\ProductVariant;
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
        try {
            $calculator = app(InvoiceCalculator::class);
            $data = array_merge($data, $calculator->calculateTotals($data['items'], $data));

            foreach ($data['items'] as $item) {
                if (!ProductVariant::find($item['variant_id'])) {
                    throw ValidationException::withMessages(['variant_id' => ["المتغير بمعرف {$item['variant_id']} غير موجود."]]);
                }
            }

            $invoice = $this->createInvoice($data);
            if (!$invoice || !$invoice->id) throw new \Exception('فشل في إنشاء الفاتورة.');

            $this->createInvoiceItems($invoice, $data['items'], $data['company_id'] ?? null, $data['created_by'] ?? null);
            $this->incrementStockForItems($data['items'], $data['company_id'] ?? null, $data['created_by'] ?? null, $data['warehouse_id'] ?? null);

            $this->accounting->recordInvoiceCreation($invoice, [
                'cash_box_id' => $data['cash_box_id'] ?? null,
                'user_cash_box_id' => $data['user_cash_box_id'] ?? null
            ]);

            return $invoice;
        } catch (\Throwable $e) {
            Log::error('PurchaseInvoiceService: فشل في إنشاء فاتورة الشراء.', ['error' => $e->getMessage()]);
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
