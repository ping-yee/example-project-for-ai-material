<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class TaskController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $tasks = Task::orderBy('created_at', 'desc')->get();
        return response()->json([
            'success' => true,
            'data' => $tasks
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'status' => 'nullable|in:pending,in_progress,completed',
                'priority' => 'nullable|integer|in:1,2,3',
                'due_date' => 'nullable|date'
            ]);

            $task = Task::create($validated);

            return response()->json([
                'success' => true,
                'message' => '任務建立成功',
                'data' => $task
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => '驗證失敗',
                'errors' => $e->errors()
            ], 422);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $task = Task::find($id);
        
        if (!$task) {
            return response()->json([
                'success' => false,
                'message' => '任務不存在'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $task
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $task = Task::find($id);
            
            if (!$task) {
                return response()->json([
                    'success' => false,
                    'message' => '任務不存在'
                ], 404);
            }

            $validated = $request->validate([
                'title' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'status' => 'sometimes|in:pending,in_progress,completed',
                'priority' => 'sometimes|integer|in:1,2,3',
                'due_date' => 'nullable|date'
            ]);

            $task->update($validated);

            return response()->json([
                'success' => true,
                'message' => '任務更新成功',
                'data' => $task
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => '驗證失敗',
                'errors' => $e->errors()
            ], 422);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $task = Task::find($id);
        
        if (!$task) {
            return response()->json([
                'success' => false,
                'message' => '任務不存在'
            ], 404);
        }

        $task->delete();

        return response()->json([
            'success' => true,
            'message' => '任務刪除成功'
        ]);
    }

    // 幫我實作搜尋功能，透過 title 搜尋
    public function search(Request $request): JsonResponse
    {
        $query = $request->input('query');
        $tasks = Task::where('title', 'like', "%$query%")->get();
        return response()->json([
            'success' => true,
            'data' => $tasks
        ]);
    }

    // 篩選和排序功能
    public function filter(Request $request): JsonResponse
    {
        $query = Task::query();

        // 狀態篩選
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // 優先級篩選
        if ($request->has('priority') && $request->priority !== 'all') {
            $query->where('priority', $request->priority);
        }

        // 日期範圍篩選
        if ($request->has('date_from') && $request->date_from) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
        }

        // 排序
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        
        // 驗證排序欄位
        $allowedSortFields = ['created_at', 'updated_at', 'title', 'priority', 'status', 'due_date'];
        if (!in_array($sortBy, $allowedSortFields)) {
            $sortBy = 'created_at';
        }

        // 驗證排序方向
        if (!in_array($sortOrder, ['asc', 'desc'])) {
            $sortOrder = 'desc';
        }

        $query->orderBy($sortBy, $sortOrder);

        $tasks = $query->get();

        return response()->json([
            'success' => true,
            'data' => $tasks,
            'filters' => [
                'status' => $request->status,
                'priority' => $request->priority,
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder
            ]
        ]);
    }
}
