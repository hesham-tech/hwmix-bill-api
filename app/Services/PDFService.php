<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class PDFService
{
    /**
     * Generate Invoice PDF
     *
     * @param Invoice $invoice
     * @return \Illuminate\Http\Response
     */
    public function generateInvoicePDF(Invoice $invoice)
    {
        // Load relationships
        $invoice->load(['items.product', 'items.variant', 'customer', 'company', 'invoiceType', 'payments']);

        // Generate PDF
        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'company' => $invoice->company,
            'customer' => $invoice->customer,
            'items' => $invoice->items,
        ]);

        // Set paper size and orientation
        $pdf->setPaper('a4', 'portrait');

        // Return download response
        return $pdf->download("invoice_{$invoice->invoice_number}.pdf");
    }

    /**
     * Generate Invoice PDF and return as string (for email)
     *
     * @param Invoice $invoice
     * @return string
     */
    public function generateInvoicePDFString(Invoice $invoice): string
    {
        $invoice->load(['items.product', 'items.variant', 'customer', 'company', 'invoiceType', 'payments']);

        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'company' => $invoice->company,
            'customer' => $invoice->customer,
            'items' => $invoice->items,
        ]);

        return $pdf->output();
    }

    /**
     * Generate Receipt PDF
     *
     * @param InvoicePayment $payment
     * @return \Illuminate\Http\Response
     */
    public function generateReceiptPDF(InvoicePayment $payment)
    {
        $payment->load(['invoice.customer', 'invoice.company', 'paymentMethod', 'cashBox']);

        $pdf = Pdf::loadView('pdf.receipt', [
            'payment' => $payment,
            'invoice' => $payment->invoice,
            'company' => $payment->invoice->company,
            'customer' => $payment->invoice->customer,
        ]);

        $pdf->setPaper('a4', 'portrait');

        return $pdf->download("receipt_{$payment->id}.pdf");
    }

    /**
     * Generate Report PDF
     *
     * @param array $data
     * @param string $template
     * @param string $filename
     * @return \Illuminate\Http\Response
     */
    public function generateReportPDF(array $data, string $template = 'report', string $filename = 'report'): \Illuminate\Http\Response
    {
        $pdf = Pdf::loadView("pdf.reports.{$template}", $data);

        $pdf->setPaper('a4', 'portrait');

        return $pdf->download("{$filename}.pdf");
    }

    /**
     * Email PDF
     *
     * @param Invoice $invoice
     * @param array $recipients
     * @param string $subject
     * @return bool
     */
    public function emailInvoicePDF(Invoice $invoice, array $recipients, string $subject = null): bool
    {
        try {
            $pdfContent = $this->generateInvoicePDFString($invoice);
            $subject = $subject ?? "فاتورة رقم {$invoice->invoice_number}";

            Mail::send('emails.invoice', ['invoice' => $invoice], function ($message) use ($recipients, $subject, $pdfContent, $invoice) {
                $message->to($recipients)
                    ->subject($subject)
                    ->attachData($pdfContent, "invoice_{$invoice->invoice_number}.pdf", [
                        'mime' => 'application/pdf',
                    ]);
            });

            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to email PDF: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Save PDF to storage
     *
     * @param Invoice $invoice
     * @param string $disk
     * @return string|false Path to saved file or false on failure
     */
    public function saveInvoicePDF(Invoice $invoice, string $disk = 'local')
    {
        try {
            $pdfContent = $this->generateInvoicePDFString($invoice);
            $filename = "invoices/invoice_{$invoice->invoice_number}_{$invoice->id}.pdf";

            Storage::disk($disk)->put($filename, $pdfContent);

            return $filename;
        } catch (\Exception $e) {
            \Log::error('Failed to save PDF: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate PDF for multiple invoices (batch)
     *
     * @param \Illuminate\Support\Collection $invoices
     * @return \Illuminate\Http\Response
     */
    public function generateBatchInvoicesPDF($invoices)
    {
        $invoices->load(['items.product', 'customer', 'company']);

        $pdf = Pdf::loadView('pdf.batch_invoices', [
            'invoices' => $invoices,
        ]);

        $pdf->setPaper('a4', 'portrait');

        return $pdf->download("invoices_batch_" . now()->format('Y-m-d') . ".pdf");
    }
}
