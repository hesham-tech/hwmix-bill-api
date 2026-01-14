<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TaskController extends Controller
{
    /**
     * Display a listing of the tasks.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Task::query()->with(['creator', 'assignments.assignable', 'attachments']);

        // Filter based on user assignment (or if created by user)
        $query->where(function ($q) use ($user) {
            $q->where('created_by', $user->id)
                ->orWhereHas('assignments', function ($assignmentQ) use ($user) {
                    $assignmentQ->where(function ($subQ) use ($user) {
                        $subQ->where('assignable_type', \App\Models\User::class)
                            ->where('assignable_id', $user->id);
                    })->orWhere(function ($subQ) use ($user) {
                        $subQ->where('assignable_type', \App\Models\TaskGroup::class)
                            ->whereIn('assignable_id', $user->taskGroups()->pluck('task_groups.id')); // Fixed ambiguity
                    });
                });
        });

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->has('search') && $request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', '%' . $request->search . '%')
                    ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        return api_success($query->latest()->paginate($request->per_page ?? 15));
    }

    /**
     * Store a newly created task in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'required|in:urgent,high,medium,low',
            'deadline' => 'nullable|date',
            'assignments' => 'required|array', // Array of {type: 'user'|'group', id: number}
        ]);

        DB::beginTransaction();
        try {
            $task = Task::create([
                'company_id' => $request->user()->company_id,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'priority' => $validated['priority'],
                'deadline' => $validated['deadline'] ?? null,
                'status' => 'pending',
                'created_by' => $request->user()->id,
            ]);

            foreach ($validated['assignments'] as $assign) {
                $type = $assign['type'] === 'user' ? \App\Models\User::class : \App\Models\TaskGroup::class;
                $task->assignments()->create([
                    'assignable_type' => $type,
                    'assignable_id' => $assign['id'],
                ]);
            }

            // Log activity
            $task->activities()->create([
                'user_id' => $request->user()->id,
                'type' => 'created',
                'content' => 'قام بإنشاء المهمة',
            ]);

            DB::commit();

            event(new \App\Events\TaskUpdated($task, 'تم إنشاء مهمة جديدة'));

            // TODO: Dispatch real-time event

            return api_success($task->load(['assignments.assignable', 'creator']), 'تم إنشاء المهمة بنجاح');
        } catch (\Exception $e) {
            DB::rollBack();
            return api_error('فشل في إنشاء المهمة: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified task.
     */
    public function show(Task $task)
    {
        return api_success($task->load(['creator', 'assignments.assignable', 'activities.user', 'attachments']));
    }

    /**
     * Update the specified task status/progress.
     */
    public function update(Request $request, Task $task)
    {
        $validated = $request->validate([
            'status' => 'nullable|in:pending,doing,review,completed,cancelled',
            'progress' => 'nullable|integer|min:0|max:100',
            'description' => 'nullable|string',
            'title' => 'nullable|string|max:255',
        ]);

        $oldStatus = $task->status;
        $task->update($validated);

        if (isset($validated['status']) && $oldStatus !== $validated['status']) {
            $task->activities()->create([
                'user_id' => $request->user()->id,
                'type' => 'status_change',
                'content' => "قام بتغيير الحالة من {$oldStatus} إلى {$validated['status']}",
                'metadata' => ['old' => $oldStatus, 'new' => $validated['status']]
            ]);
        }

        event(new \App\Events\TaskUpdated($task, 'تم تحديث المهمة'));

        return api_success($task, 'تم تحديث المهمة بنجاح');
    }

    /**
     * Add a comment/activity to the task.
     */
    public function addComment(Request $request, Task $task)
    {
        $request->validate(['content' => 'required|string']);

        $activity = $task->activities()->create([
            'user_id' => $request->user()->id,
            'type' => 'comment',
            'content' => $request->input('content'),
        ]);

        return api_success($activity->load('user'), 'تم إضافة التعليق');
    }

    /**
     * Upload an attachment to the task.
     */
    public function uploadAttachment(Request $request, Task $task)
    {
        $request->validate([
            'file' => 'required|file|max:10240', // 10MB limit
        ]);

        $file = $request->file('file');
        $path = $file->store('tasks/attachments', 'public');

        $attachment = $task->attachments()->create([
            'user_id' => $request->user()->id,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
        ]);

        $task->activities()->create([
            'user_id' => $request->user()->id,
            'type' => 'attachment_uploaded',
            'content' => "قام برفع ملف: {$file->getClientOriginalName()}",
        ]);

        return api_success($attachment, 'تم رفع الملف بنجاح');
    }
}
