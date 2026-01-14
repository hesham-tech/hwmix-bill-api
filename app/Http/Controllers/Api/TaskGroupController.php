<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TaskGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaskGroupController extends Controller
{
    /**
     * List all task groups for the active company.
     */
    public function index(Request $request)
    {
        return api_success(TaskGroup::with('users')->withCount('users')->latest()->get());
    }

    /**
     * Store a new task group.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:20',
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        DB::beginTransaction();
        try {
            $group = TaskGroup::create([
                'company_id' => $request->user()->company_id,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'color' => $validated['color'] ?? '#primary',
                'created_by' => $request->user()->id,
            ]);

            $group->users()->sync($validated['user_ids']);

            DB::commit();
            return api_success($group->load('users'), 'تم إنشاء المجموعة بنجاح');
        } catch (\Exception $e) {
            DB::rollBack();
            return api_error('فشل في إنشاء المجموعة: ' . $e->getMessage());
        }
    }

    /**
     * Update an existing task group.
     */
    public function update(Request $request, TaskGroup $taskGroup)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:20',
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        DB::beginTransaction();
        try {
            $taskGroup->update($request->only(['name', 'description', 'color']));
            $taskGroup->users()->sync($validated['user_ids']);

            DB::commit();
            return api_success($taskGroup->load('users'), 'تم تحديث المجموعة بنجاح');
        } catch (\Exception $e) {
            DB::rollBack();
            return api_error('فشل في تحديث المجموعة: ' . $e->getMessage());
        }
    }

    /**
     * Delete a task group.
     */
    public function destroy(TaskGroup $taskGroup)
    {
        $taskGroup->delete();
        return api_success(null, 'تم حذف المجموعة بنجاح');
    }
}
