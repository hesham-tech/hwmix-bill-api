<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

abstract class BaseReportController extends Controller
{
    /**
     * Apply common filters to query
     */
    protected function applyFilters(Builder $query, array $filters): Builder
    {
        // Date range filtering
        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        // Company scope
        if (!empty($filters['company_id'])) {
            $query->where('company_id', $filters['company_id']);
        } elseif (method_exists($query->getModel(), 'scopeWhereCompanyIsCurrent')) {
            $query->whereCompanyIsCurrent();
        }

        // User/Customer filtering
        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        // Product filtering
        if (!empty($filters['product_id'])) {
            $query->whereHas('items', function ($q) use ($filters) {
                $q->where('product_id', $filters['product_id']);
            });
        }

        // Status filtering
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query;
    }

    /**
     * Group data by period (day, week, month, year)
     */
    protected function groupByPeriod($query, string $period = 'day'): Collection
    {
        $isSqlite = \DB::getDriverName() === 'sqlite';

        if ($isSqlite) {
            $dateFormat = match ($period) {
                'day' => '%Y-%m-%d',
                'week' => '%Y-%W',
                'month' => '%Y-%m',
                'year' => '%Y',
                default => '%Y-%m-%d',
            };

            return $query->selectRaw("
                strftime('{$dateFormat}', created_at) as period,
                COUNT(*) as count,
                SUM(net_amount) as total_amount,
                SUM(paid_amount) as total_paid,
                SUM(remaining_amount) as total_remaining
            ")
                ->groupBy('period')
                ->orderBy('period')
                ->get();
        }

        $dateFormat = match ($period) {
            'day' => '%Y-%m-%d',
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            'year' => '%Y',
            default => '%Y-%m-%d',
        };

        return $query->selectRaw("
                DATE_FORMAT(created_at, '{$dateFormat}') as period,
                COUNT(*) as count,
                SUM(net_amount) as total_amount,
                SUM(paid_amount) as total_paid,
                SUM(remaining_amount) as total_remaining
            ")
            ->groupBy('period')
            ->orderBy('period')
            ->get();
    }

    /**
     * Group by product
     */
    protected function groupByProduct($query): Collection
    {
        return \DB::table('invoice_items')
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->join('products', 'invoice_items.product_id', '=', 'products.id')
            ->whereIn('invoices.id', $query->pluck('id'))
            ->select([
                'products.id as product_id',
                'products.name as product_name',
                \DB::raw('SUM(invoice_items.quantity) as total_quantity'),
                \DB::raw('SUM(invoice_items.total) as total_sales'),
                \DB::raw('COUNT(DISTINCT invoices.id) as invoice_count'),
                \DB::raw('AVG(invoice_items.unit_price) as avg_price'),
            ])
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_sales')
            ->get();
    }

    /**
     * Group by customer/user
     */
    protected function groupByCustomer($query): Collection
    {
        return $query->join('users', 'invoices.user_id', '=', 'users.id')
            ->select([
                'invoices.user_id',
                'users.full_name as customer_name',
                \DB::raw('COUNT(*) as invoice_count'),
                \DB::raw('SUM(invoices.net_amount) as total_amount'),
                \DB::raw('SUM(invoices.paid_amount) as total_paid'),
                \DB::raw('SUM(invoices.remaining_amount) as total_remaining'),
                \DB::raw('AVG(invoices.net_amount) as avg_invoice')
            ])
            ->groupBy('invoices.user_id', 'users.full_name')
            ->orderByDesc('total_amount')
            ->get();
    }

    /**
     * Calculate summary statistics
     */
    protected function calculateSummary($query): array
    {
        $invoices = $query->get();

        return [
            'total_invoices' => $invoices->count(),
            'total_amount' => round($invoices->sum('net_amount'), 2),
            'total_paid' => round($invoices->sum('paid_amount'), 2),
            'total_remaining' => round($invoices->sum('remaining_amount'), 2),
            'average_invoice' => $invoices->count() > 0
                ? round($invoices->avg('net_amount'), 2)
                : 0,
            'total_tax' => round($invoices->sum('total_tax'), 2),
            'total_discount' => round($invoices->sum('total_discount'), 2),
        ];
    }

    /**
     * Export report to different formats
     */
    protected function export($data, string $format, string $filename = 'report')
    {
        return match ($format) {
            'pdf' => $this->exportPDF($data, $filename),
            'excel' => $this->exportExcel($data, $filename),
            'csv' => $this->exportCSV($data, $filename),
            default => $data,
        };
    }

    /**
     * Export to PDF using PDFService
     */
    protected function exportPDF($data, string $filename): \Illuminate\Http\Response
    {
        try {
            return app(\App\Services\PDFService::class)->generateReportPDF($data, 'report', $filename);
        } catch (\Exception $e) {
            \Log::error('PDF export failed: ' . $e->getMessage());

            // Fallback to JSON if PDF fails
            return response()->json([
                'message' => 'PDF export temporarily unavailable, here is the data',
                'data' => $data,
            ]);
        }
    }

    /**
     * Export to Excel
     */
    protected function exportExcel($data, string $filename)
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\ReportsExport($data),
            "{$filename}.xlsx"
        );
    }

    /**
     * Export to CSV
     */
    protected function exportCSV($data, string $filename)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}.csv\"",
        ];

        $callback = function () use ($data) {
            $file = fopen('php://output', 'w');

            // Write headers
            if (!empty($data) && is_array($data)) {
                $firstRow = reset($data);
                if (is_array($firstRow) || is_object($firstRow)) {
                    $headers = array_keys((array) $firstRow);
                    fputcsv($file, $headers);
                }
            }

            // Write data
            foreach ($data as $row) {
                fputcsv($file, (array) $row);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Validate common report filters
     */
    protected function validateFilters(Request $request): array
    {
        return $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'company_id' => 'nullable|exists:companies,id',
            'user_id' => 'nullable|exists:users,id',
            'product_id' => 'nullable|exists:products,id',
            'status' => 'nullable|string',
            'group_by' => 'nullable|in:day,week,month,year,product,customer',
            'export' => 'nullable|in:pdf,excel,csv',
            'per_page' => 'nullable|integer|min:10|max:100',
        ]);
    }
}
