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
}
