<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ProductVariant; // قد لا تكون ضرورية لفاتورة الخدمة، ولكن تم تضمينها كقالب
use App\Models\Stock; // قد لا تكون ضرورية لفاتورة الخدمة، ولكن تم تضمينها كقالب
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Services\AccountingService;
use App\Services\DocumentServiceInterface;
use App\Services\Traits\InvoiceHelperTrait;
use Illuminate\Support\Facades\Log;

class ServiceInvoiceService implements DocumentServiceInterface
{
    use InvoiceHelperTrait;

    protected AccountingService $accounting;

    public function __construct(AccountingService $accounting)
    {
        $this->accounting = $accounting;
    }

    /**
     * إنشاء فاتورة خدمة جديدة.
     *
     * @param array $data بيانات الفاتورة.
     * @return Invoice الفاتورة التي تم إنشاؤها.
     * @throws ValidationException
     * @throws \Throwable
     */
    public function create(array $data): Invoice
    {
        try {
            Log::info('ServiceInvoiceService: بدء إنشاء فاتورة خدمة.', ['data' => $data]);

            // 1. إنشاء الفاتورة الرئيسية
            $invoice = $this->createInvoice($data);
            if (!$invoice || !$invoice->id) {
                throw new \Exception('فشل في إنشاء الفاتورة.');
            }

            // 2. إنشاء بنود الفاتورة (خدمات)
            $this->createInvoiceItems($invoice, $data['items'], $data['company_id'] ?? null, $data['created_by'] ?? null);


            $authUser = Auth::user();
            $cashBoxId = $data['cash_box_id'] ?? null;
            $userCashBoxId = $data['user_cash_box_id'] ?? null;

            // ✅ تسجيل الأثر المالي الموحد لفاتورة الخدمة
            $this->accounting->recordInvoiceCreation($invoice, [
                'cash_box_id' => $cashBoxId,
                'user_cash_box_id' => $userCashBoxId
            ]);

            Log::info('ServiceInvoiceService: تم إنشاء فاتورة الخدمة بنجاح.', ['invoice_id' => $invoice->id]);
            return $invoice;
        } catch (\Throwable $e) {
            Log::error('ServiceInvoiceService: فشل في إنشاء فاتورة الخدمة.', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

    /**
     * تحديث فاتورة خدمة موجودة.
     *
     * @param array $data البيانات الجديدة للفاتورة.
     * @param Invoice $invoice الفاتورة المراد تحديثها.
     * @return Invoice الفاتورة المحدثة.
     * @throws \Throwable
     */
    public function update(array $data, Invoice $invoice): Invoice
    {
        try {
            Log::info('ServiceInvoiceService: بدء تحديث فاتورة خدمة.', ['invoice_id' => $invoice->id, 'data' => $data]);

            $freshInvoice = Invoice::find($invoice->id);
            if (!$freshInvoice) {
                throw new \Exception("فشل العثور على الفاتورة (ID: {$invoice->id}) أثناء التحديث.");
            }

            $authUser = Auth::user();
            $cashBoxId = $data['cash_box_id'] ?? null;
            $userCashBoxId = $data['user_cash_box_id'] ?? null;

            // ✅ عكس الأثر المالي القديم قبل التحديث
            $this->accounting->reverseInvoice($freshInvoice, [
                'cash_box_id' => $freshInvoice->cash_box_id,
                'user_cash_box_id' => $freshInvoice->user_cash_box_id
            ]);

            // تحديث البيانات
            $this->updateInvoice($invoice, $data);
            $this->syncInvoiceItems($invoice, $data['items'], $data['company_id'] ?? null, $data['updated_by'] ?? null);

            // ✅ تسجيل الأثر المالي الجديد للفاتورة المحدثة
            $this->accounting->recordInvoiceCreation($invoice, [
                'cash_box_id' => $cashBoxId,
                'user_cash_box_id' => $userCashBoxId
            ]);

            Log::info('ServiceInvoiceService: تم تحديث فاتورة الخدمة بنجاح.', ['invoice_id' => $invoice->id]);

            return $invoice;
        } catch (\Throwable $e) {
            Log::error('ServiceInvoiceService: فشل في تحديث فاتورة الخدمة.', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

    /**
     * إلغاء فاتورة خدمة.
     *
     * @param Invoice $invoice الفاتورة المراد إلغاؤها.
     * @return Invoice الفاتورة الملغاة.
     * @throws \Exception إذا كانت الفاتورة مدفوعة بالكامل.
     * @throws \Throwable
     */
    public function cancel(Invoice $invoice): Invoice
    {
        try {
            Log::info('ServiceInvoiceService: بدء إلغاء فاتورة خدمة.', ['invoice_id' => $invoice->id]);
            
            // تغيير حالة الفاتورة
            $invoice->update(['status' => 'canceled']);
            $this->deleteInvoiceItems($invoice);

            // ✅ عكس الأثر المالي للفاتورة الملغاة
            $this->accounting->reverseInvoice($invoice, [
                'cash_box_id' => $invoice->cash_box_id,
                'user_cash_box_id' => $invoice->user_cash_box_id
            ]);

            Log::info('ServiceInvoiceService: تم إلغاء فاتورة الخدمة بنجاح.', ['invoice_id' => $invoice->id]);
            return $invoice;
        } catch (\Throwable $e) {
            Log::error('ServiceInvoiceService: فشل في إلغاء فاتورة الخدمة.', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }
}
