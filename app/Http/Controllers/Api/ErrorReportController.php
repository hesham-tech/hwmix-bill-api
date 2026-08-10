<?php

// متحكم استلام واستعراض تقارير الأخطاء من الواجهات وتطبيق النظام.

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
        if (!Auth::user()->hasPermissionTo('admin.super')) {
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
            Log::info('Incoming Error Report:', $request->all());

            // Robust payload handling
            $payload = $request->input('payload');
            if (is_string($payload)) {
                $decoded = json_decode($payload, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $payload = $decoded;
                } else {
                    Log::warning('Failed to decode error report payload JSON:', ['raw' => $payload]);
                    $payload = ['raw_text' => $payload];
                }
            }

            $validated = $request->validate([
                'message' => 'required|string',
                'type' => 'nullable|string',
                'stack_trace' => 'nullable|string',
                'url' => 'nullable|string',
                'browser' => 'nullable|string',
                'os' => 'nullable|string',
                'user_notes' => 'nullable|string',
                'severity' => 'nullable|string',
                'screenshot' => 'nullable|image|max:10240', // Increased to 10MB just in case
            ]);

            $screenshotUrl = null;
            if ($request->hasFile('screenshot')) {
                try {
                    $file = $request->file('screenshot');
                    $filename = 'report_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                    $path = $file->storeAs('error_reports', $filename, 'public');
                    $screenshotUrl = '/storage/' . $path;
                    Log::info('Screenshot saved at: ' . $screenshotUrl);
                } catch (\Exception $e) {
                    Log::error('Failed to save report screenshot: ' . $e->getMessage());
                    // Don't fail the whole report if only screenshot fails
                }
            }

            // محاولة جلب المستخدم الحالي بشكل اختياري عبر Sanctum
            $user = Auth::guard('sanctum')->user();
            $userId = $user ? $user->id : null;
            $companyId = $user ? $user->active_company_id : null;

            // 1. Append to physical error.log file FIRST to ensure logs are captured even if DB table is missing
            try {
                $logPath = base_path('error.log');
                $timestamp = date('Y-m-d H:i:s');
                $userName = $user ? ($user->name ?? $user->username ?? 'User') : 'Guest';
                $reportType = $validated['type'] ?? 'error';
                $reportMsg = $validated['message'] ?? 'No message';
                $reportSeverity = $validated['severity'] ?? 'medium';
                $reportBrowser = $validated['browser'] ?? 'Unknown';
                $reportOs = $validated['os'] ?? 'Unknown';
                $reportUrl = $validated['url'] ?? 'No URL';
                $reportNotes = $validated['user_notes'] ?? null;
                $reportTrace = $validated['stack_trace'] ?? null;

                $logMessage = "[$timestamp] " . strtoupper($reportType) . ": " . $reportMsg . PHP_EOL;
                $logMessage .= "Severity: " . strtoupper($reportSeverity) . " | Browser: " . $reportBrowser . " | OS: " . $reportOs . PHP_EOL;
                $logMessage .= "URL: " . $reportUrl . PHP_EOL;
                $logMessage .= "User: " . ($userId ?? 'Guest') . " ($userName)";

                if ($reportNotes) {
                    $logMessage .= " | Notes: " . $reportNotes;
                }
                $logMessage .= PHP_EOL;

                if (!empty($payload)) {
                    $payloadStr = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                    $logMessage .= "Payload: " . $payloadStr . PHP_EOL;
                }

                if ($reportTrace) {
                    $logMessage .= "Stack Trace Snippet: " . substr($reportTrace, 0, 500) . (strlen($reportTrace) > 500 ? '...' : '') . PHP_EOL;
                }

                $logMessage .= str_repeat('-', 50) . PHP_EOL;

                file_put_contents($logPath, $logMessage, FILE_APPEND);
            } catch (\Exception $e) {
                Log::warning('Failed to append to browser error.log: ' . $e->getMessage());
            }

            // 2. Attempt DB creation (gracefully handle if table or migration is missing on production)
            $reportId = null;
            try {
                $report = ErrorReport::create([
                    'user_id' => $userId,
                    'company_id' => $companyId,
                    'type' => $validated['type'] ?? 'error',
                    'message' => $validated['message'],
                    'stack_trace' => $validated['stack_trace'] ?? null,
                    'url' => $validated['url'] ?? null,
                    'browser' => $validated['browser'] ?? null,
                    'os' => $validated['os'] ?? null,
                    'user_notes' => $validated['user_notes'] ?? null,
                    'payload' => $payload,
                    'severity' => $validated['severity'] ?? 'medium',
                    'screenshot_url' => $screenshotUrl,
                    'status' => 'pending',
                ]);
                $reportId = $report->id;
            } catch (\Exception $e) {
                Log::warning('Could not save ErrorReport to database (migration pending?): ' . $e->getMessage());
            }

            return response()->json([
                'message' => 'تم استلام تقرير الخطأ بنجاح، شكراً لمساعدتنا في تحسين النظام.',
                'report_id' => $reportId,
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Error Report Store Failure: ' . $e->getMessage(), [
                'exception' => $e,
                'request' => $request->all(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'message' => 'حدث خطأ أثناء حفظ تقرير الخطأ.',
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
            ], 500);
        }
    }

    /**
     * Update report status (Super Admin only).
     */
    public function update(Request $request, ErrorReport $errorReport)
    {
        if (!Auth::user()->hasPermissionTo('admin.super')) {
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
