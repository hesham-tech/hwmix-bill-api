<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ActivityController extends Controller
{
    /**
     * Get activity logs with filters
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'subject_type' => 'nullable|string',
            'subject_id' => 'nullable|integer',
            'action' => 'nullable|string',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'per_page' => 'nullable|integer|min:10|max:100',
        ]);

        $query = Activity::query()->with(['user', 'company']);

        // Apply filters
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('subject_type')) {
            $query->where('subject_type', $request->subject_type);
        }

        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->subject_id);
        }

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Sort by latest
        $query->latest();

        $activities = $query->paginate($request->input('per_page', 50));

        return response()->json([
            'success' => true,
            'data' => $activities,
        ]);
    }

    /**
     * Get activity log by ID
     */
    public function show($id): JsonResponse
    {
        $activity = Activity::with(['user', 'company', 'subject'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $activity,
        ]);
    }

    /**
     * Get activities for a specific subject
     */
    public function forSubject(Request $request): JsonResponse
    {
        $request->validate([
            'subject_type' => 'required|string',
            'subject_id' => 'required|integer',
        ]);

        $activities = Activity::query()
            ->with(['user', 'company'])
            ->where('subject_type', $request->subject_type)
            ->where('subject_id', $request->subject_id)
            ->latest()
            ->paginate(50);

        return response()->json([
            'success' => true,
            'data' => $activities,
        ]);
    }

    /**
     * Get user activity
     */
    public function userActivity($userId, Request $request): JsonResponse
    {
        $activities = Activity::query()
            ->with(['company', 'subject'])
            ->where('user_id', $userId)
            ->latest()
            ->paginate($request->input('per_page', 50));

        return response()->json([
            'success' => true,
            'data' => $activities,
        ]);
    }

    /**
     * Get invoice activities
     */
    public function invoiceActivities($invoiceId): JsonResponse
    {
        $activities = Activity::query()
            ->with(['user', 'company'])
            ->where('subject_type', 'App\\Models\\Invoice')
            ->where('subject_id', $invoiceId)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $activities,
        ]);
    }

    /**
     * Export activity logs
     */
    public function export(Request $request)
    {
        $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'format' => 'nullable|in:csv,excel',
        ]);

        $activities = Activity::query()
            ->with(['user', 'company'])
            ->whereBetween('created_at', [$request->date_from, $request->date_to])
            ->get();

        $format = $request->input('format', 'csv');

        if ($format === 'excel') {
            return \Maatwebsite\Excel\Facades\Excel::download(
                new \App\Exports\ActivityLogsExport($activities),
                'activity_logs.xlsx'
            );
        }

        return $this->exportCSV($activities);
    }

    /**
     * Export to CSV
     */
    private function exportCSV($activities)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="activity_logs.csv"',
        ];

        $callback = function () use ($activities) {
            $file = fopen('php://output', 'w');

            // Headers
            fputcsv($file, ['ID', 'User', 'Action', 'Description', 'Subject', 'Date']);

            // Data
            foreach ($activities as $activity) {
                fputcsv($file, [
                    $activity->id,
                    $activity->user?->name ?? 'System',
                    $activity->action,
                    $activity->description,
                    $activity->subject_type ? class_basename($activity->subject_type) . ' #' . $activity->subject_id : '-',
                    $activity->created_at->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
