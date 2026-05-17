<?php

namespace Modules\Sales\Services;

use Modules\Sales\Models\Invoice;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Services\DocumentServiceInterface;
use Modules\Sales\Services\Traits\InvoiceHelperTrait;
use App\Services\InstallmentService;
use Modules\Accounting\Services\AccountingService;
use Illuminate\Support\Facades\Log;

class InstallmentSaleInvoiceService implements DocumentServiceInterface
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
            $this->checkVariantsStock($data['items'], 'deduct', $data['warehouse_id'] ?? null);
            $invoice = $this->createInvoice($data);
            if (!$invoice || !$invoice->id) throw new \Exception('فشل في إنشاء الفاتورة.');

            $this->createInvoiceItems($invoice, $data['items'], $data['company_id'] ?? null, $data['created_by'] ?? null);
            $this->deductStockForItems($data['items'], $data['warehouse_id'] ?? null);

            $this->accounting->recordInvoiceCreation($invoice, [
                'cash_box_id' => $data['cash_box_id'] ?? null,
                'user_cash_box_id' => $data['user_cash_box_id'] ?? null
            ]);

            if (isset($data['installment_plan'])) {
                app(InstallmentService::class)->createInstallments($data, $invoice->id);
            }

            return $invoice;
        } catch (\Throwable $e) {
            Log::error('InstallmentSaleInvoiceService: فشل في إنشاء فاتورة بيع بالتقسيط.', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function update(array $data, Invoice $invoice): Invoice
    {
        try {
            $this->cancel($invoice);
            return $this->create($data);
        } catch (\Throwable $e) {
            Log::error('InstallmentSaleInvoiceService: فشل في تحديث فاتورة بيع بالتقسيط.', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function cancel(Invoice $invoice): Invoice
    {
        try {
            $this->returnStockForItems($invoice);
            if ($invoice->installmentPlan) app(InstallmentService::class)->cancelInstallments($invoice);

            $this->accounting->reverseInvoice($invoice, [
                'cash_box_id' => $invoice->cash_box_id,
                'user_cash_box_id' => $invoice->user_cash_box_id
            ]);

            $invoice->update(['status' => 'canceled']);
            $this->deleteInvoiceItems($invoice);

            return $invoice;
        } catch (\Throwable $e) {
            Log::error('InstallmentSaleInvoiceService: فشل في إلغاء فاتورة بيع بالتقسيط.', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
