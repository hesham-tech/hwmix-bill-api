<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ErrorReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ErrorReportController extends Controller
{
    /**
     * Display a listing of error reports (Super Admin only).
     */
    public function index()
    {
        // Permission check is usually handled via middleware in routes, 
        // but adding local check for safety.
        if (!Auth::user()->hasPermission('admin.super')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $reports = ErrorReport::with(['user', 'company'])
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return response()->json($reports);
    }

    /**
     * Store a new error report.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'message' => 'required|string',
                'type' => 'nullable|string',
                'stack_trace' => 'nullable|string',
                'url' => 'nullable|string',
                'browser' => 'nullable|string',
                'os' => 'nullable|string',
                'user_notes' => 'nullable|string',
                'payload' => 'nullable|array',
                'severity' => 'nullable|string',
            ]);

            $report = ErrorReport::create([
                'user_id' => Auth::id(),
                'company_id' => Auth::user()?->company_id,
                'type' => $validated['type'] ?? 'error',
                'message' => $validated['message'],
                'stack_trace' => $validated['stack_trace'] ?? null,
                'url' => $validated['url'] ?? null,
                'browser' => $validated['browser'] ?? null,
                'os' => $validated['os'] ?? null,
                'user_notes' => $validated['user_notes'] ?? null,
                'payload' => $validated['payload'] ?? null,
                'severity' => $validated['severity'] ?? 'medium',
                'status' => 'pending',
            ]);

            return response()->json([
                'message' => 'تم استلام تقرير الخطأ بنجاح، شكراً لمساعدتنا في تحسين النظام.',
                'report_id' => $report->id,
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Error Report Store Failure: ' . $e->getMessage(), [
                'exception' => $e,
                'request' => $request->all(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'message' => 'حدث خطأ أثناء حفظ تقرير الخطأ.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Update report status (Super Admin only).
     */
    public function update(Request $request, ErrorReport $errorReport)
    {
        if (!Auth::user()->hasPermission('admin.super')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'status' => 'required|string|in:pending,in_review,investigating,resolved,closed,ignored',
            'severity' => 'nullable|string|in:low,medium,high,critical',
        ]);

        $errorReport->update($validated);

        return response()->json(['message' => 'تم تحديث حالة التقرير بنجاح.']);
    }
}
