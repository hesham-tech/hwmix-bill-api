<?php

namespace Modules\Sales\Services;

use Modules\Sales\Models\Invoice;
use Modules\Core\Services\DocumentServiceInterface;
use Modules\Sales\Services\Traits\InvoiceHelperTrait;
use Modules\Accounting\Services\AccountingService;
use App\Services\InvoiceCalculator;
use Illuminate\Support\Facades\Log;

class ReturnService implements DocumentServiceInterface
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

            $invoice = $this->createInvoice($data);
            $this->createInvoiceItems($invoice, $data['items'], $data['company_id'] ?? null, $data['created_by'] ?? null);

            if ($invoice->invoice_type_code === 'sale_return') {
                $this->incrementStockForItems($data['items'], $data['company_id'] ?? null, $data['created_by'] ?? null, $data['warehouse_id'] ?? null);
            } elseif ($invoice->invoice_type_code === 'purchase_return') {
                $this->deductStockForItems($data['items'], $data['warehouse_id'] ?? null);
            }

            $this->accounting->recordInvoiceCreation($invoice, [
                'cash_box_id' => $data['cash_box_id'] ?? null,
                'user_cash_box_id' => $data['user_cash_box_id'] ?? null
            ]);

            return $invoice;
        } catch (\Throwable $e) {
            Log::error('ReturnService: فشل إنشاء الفاتورة.', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function update(array $data, Invoice $invoice): Invoice
    {
        try {
            $this->accounting->reverseInvoice($invoice, [
                'cash_box_id' => $invoice->cash_box_id,
                'user_cash_box_id' => $invoice->user_cash_box_id
            ]);

            if ($invoice->invoice_type_code === 'sale_return') {
                $this->decrementStockForInvoiceItems($invoice);
            } elseif ($invoice->invoice_type_code === 'purchase_return') {
                $this->returnStockForItems($invoice);
            }

            $calculator = app(InvoiceCalculator::class);
            $data = array_merge($data, $calculator->calculateTotals($data['items'], $data));

            $this->updateInvoice($invoice, $data);
            $this->syncInvoiceItems($invoice, $data['items'], $data['company_id'] ?? null, $data['updated_by'] ?? null);

            if ($invoice->invoice_type_code === 'sale_return') {
                $this->incrementStockForItems($data['items'], $data['company_id'] ?? null, $data['updated_by'] ?? null, $data['warehouse_id'] ?? null);
            } elseif ($invoice->invoice_type_code === 'purchase_return') {
                $this->deductStockForItems($data['items'], $data['warehouse_id'] ?? null);
            }

            $this->accounting->recordInvoiceCreation($invoice, [
                'cash_box_id' => $data['cash_box_id'] ?? null,
                'user_cash_box_id' => $data['user_cash_box_id'] ?? null
            ]);

            return $invoice;
        } catch (\Throwable $e) {
            Log::error('ReturnService: فشل تحديث الفاتورة.', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function cancel(Invoice $invoice): Invoice
    {
        try {
            $this->accounting->reverseInvoice($invoice, [
                'cash_box_id' => $invoice->cash_box_id,
                'user_cash_box_id' => $invoice->user_cash_box_id
            ]);

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
