<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use App\Models\Task;
use App\Rules\TagName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class TaskController extends Controller
{
    public function tagOptions(Request $request): JsonResponse
    {
        $query = Tag::query();

        if ($request->filled('query')) {
            $query->where('name', 'like', '%'.$request->string('query')->trim().'%');
        }

        return response()->json([
            'success' => true,
            'data' => $query->orderBy('name')->orderBy('id')->get(),
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $tasks = Task::orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $tasks,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'status' => 'nullable|in:pending,in_progress,completed',
                'priority' => 'nullable|integer|in:1,2,3',
                'due_date' => 'nullable|date',
                'new_tag_name' => ['sometimes', 'required', 'string', new TagName],
                'tag_ids' => ['sometimes', 'array'],
                'tag_ids.*' => ['integer', 'distinct', 'exists:tags,id'],
                'new_tag_names' => ['sometimes', 'array'],
                'new_tag_names.*' => ['required', 'string', new TagName],
            ]);

            $validator->after(function ($validator) use ($request): void {
                $tagIds = $request->input('tag_ids', []);
                $newTagNames = $request->input('new_tag_names', []);
                $tagCount = (is_array($tagIds) ? count($tagIds) : 0)
                    + (is_array($newTagNames) ? count($newTagNames) : 0)
                    + ($request->has('new_tag_name') ? 1 : 0);

                if ($tagCount > 10) {
                    $validator->errors()->add('tags', '一項任務最多只能選擇 10 個標籤。');
                }

                $newTags = [];
                foreach ((array) $request->input('new_tag_names', []) as $index => $name) {
                    $newTags["new_tag_names.{$index}"] = $name;
                }

                if ($request->has('new_tag_name')) {
                    $newTags['new_tag_name'] = $request->input('new_tag_name');
                }

                $seenNames = [];
                foreach ($newTags as $field => $name) {
                    if (! is_string($name) || $name === '') {
                        continue;
                    }

                    $normalizedName = Tag::normalizeName($name);
                    $alreadyExists = isset($seenNames[$normalizedName])
                        || Tag::query()->where('normalized_name', $normalizedName)->exists();

                    if ($alreadyExists) {
                        $validator->errors()->add($field, '標籤已存在，請從選項中選取');
                    }

                    $seenNames[$normalizedName] = true;
                }
            });

            $validated = $validator->validate();

            $task = DB::transaction(function () use ($validated): Task {
                $tagIds = $validated['tag_ids'] ?? [];
                $newTagNames = $validated['new_tag_names'] ?? [];

                if (array_key_exists('new_tag_name', $validated)) {
                    $newTagNames[] = $validated['new_tag_name'];
                }

                unset($validated['new_tag_name'], $validated['new_tag_names'], $validated['tag_ids']);

                $task = Task::create($validated);

                foreach ($newTagNames as $newTagName) {
                    $tagIds[] = Tag::createFromName($newTagName)->id;
                }

                $task->tags()->sync($tagIds);

                return $task->load('tags');
            });

            return response()->json([
                'success' => true,
                'message' => '任務建立成功',
                'data' => $task,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => '驗證失敗',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $task = Task::find($id);

        if (! $task) {
            return response()->json([
                'success' => false,
                'message' => '任務不存在',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $task,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $task = Task::find($id);

            if (! $task) {
                return response()->json([
                    'success' => false,
                    'message' => '任務不存在',
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'status' => 'sometimes|in:pending,in_progress,completed',
                'priority' => 'sometimes|integer|in:1,2,3',
                'due_date' => 'nullable|date',
                'new_tag_name' => ['sometimes', 'required', 'string', new TagName],
                'tag_ids' => ['sometimes', 'array'],
                'tag_ids.*' => ['integer', 'distinct', 'exists:tags,id'],
                'new_tag_names' => ['sometimes', 'array'],
                'new_tag_names.*' => ['required', 'string', new TagName],
            ]);

            $validator->after(function ($validator) use ($request): void {
                $tagIds = $request->input('tag_ids', []);
                $newTagNames = $request->input('new_tag_names', []);
                $tagCount = (is_array($tagIds) ? count($tagIds) : 0)
                    + (is_array($newTagNames) ? count($newTagNames) : 0)
                    + ($request->has('new_tag_name') ? 1 : 0);

                if ($tagCount > 10) {
                    $validator->errors()->add('tags', '一項任務最多只能選擇 10 個標籤。');
                }

                $newTags = [];
                foreach ((array) $request->input('new_tag_names', []) as $index => $name) {
                    $newTags["new_tag_names.{$index}"] = $name;
                }

                if ($request->has('new_tag_name')) {
                    $newTags['new_tag_name'] = $request->input('new_tag_name');
                }

                $seenNames = [];
                foreach ($newTags as $field => $name) {
                    if (! is_string($name) || $name === '') {
                        continue;
                    }

                    $normalizedName = Tag::normalizeName($name);
                    $alreadyExists = isset($seenNames[$normalizedName])
                        || Tag::query()->where('normalized_name', $normalizedName)->exists();

                    if ($alreadyExists) {
                        $validator->errors()->add($field, '標籤已存在，請從選項中選取');
                    }

                    $seenNames[$normalizedName] = true;
                }
            });

            $validated = $validator->validate();
            $tagsSubmitted = $request->hasAny(['tag_ids', 'new_tag_names', 'new_tag_name']);

            $task = DB::transaction(function () use ($task, $validated, $tagsSubmitted): Task {
                $tagIds = $validated['tag_ids'] ?? [];
                $newTagNames = $validated['new_tag_names'] ?? [];

                if (array_key_exists('new_tag_name', $validated)) {
                    $newTagNames[] = $validated['new_tag_name'];
                }

                unset($validated['new_tag_name'], $validated['new_tag_names'], $validated['tag_ids']);
                $task->update($validated);

                if ($tagsSubmitted) {
                    foreach ($newTagNames as $newTagName) {
                        $tagIds[] = Tag::createFromName($newTagName)->id;
                    }

                    $task->tags()->sync($tagIds);
                }

                return $task->load('tags');
            });

            return response()->json([
                'success' => true,
                'message' => '任務更新成功',
                'data' => $task,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => '驗證失敗',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $task = Task::find($id);

        if (! $task) {
            return response()->json([
                'success' => false,
                'message' => '任務不存在',
            ], 404);
        }

        $task->delete();

        return response()->json([
            'success' => true,
            'message' => '任務刪除成功',
        ]);
    }

    // 幫我實作搜尋功能，透過 title 搜尋
    public function search(Request $request): JsonResponse
    {
        $query = $request->input('query');
        $tasks = Task::where('title', 'like', "%$query%");
        $this->applySorting($tasks, $request);

        return response()->json([
            'success' => true,
            'data' => $tasks->get(),
        ]);
    }

    // 篩選和排序功能
    public function filter(Request $request): JsonResponse
    {
        $query = Task::query();
        $tag = $request->input('tag', 'all');
        $tagFilterActive = $tag !== null && $tag !== '' && $tag !== 'all';

        if ($tagFilterActive) {
            if ($tag === 'untagged') {
                $query->doesntHave('tags');
            } elseif (ctype_digit((string) $tag)) {
                $query->whereHas('tags', function ($tagQuery) use ($tag): void {
                    $tagQuery->where('tags.id', (int) $tag);
                });
            } else {
                throw ValidationException::withMessages([
                    'tag' => ['標籤篩選條件無效。'],
                ]);
            }
        } else {
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
                $query->where('created_at', '<=', $request->date_to.' 23:59:59');
            }
        }

        [$sortBy, $sortOrder] = $this->applySorting($query, $request);

        $tasks = $query->get();

        return response()->json([
            'success' => true,
            'data' => $tasks,
            'filters' => [
                'tag' => (string) $tag,
                'status' => $request->status,
                'priority' => $request->priority,
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function applySorting(Builder $query, Request $request): array
    {
        $allowedSortFields = ['created_at', 'updated_at', 'title', 'priority', 'status', 'due_date'];
        $sortBy = (string) $request->input('sort_by', 'created_at');
        $sortOrder = (string) $request->input('sort_order', 'desc');

        if (! in_array($sortBy, $allowedSortFields, true)) {
            $sortBy = 'created_at';
        }

        if (! in_array($sortOrder, ['asc', 'desc'], true)) {
            $sortOrder = 'desc';
        }

        $query->orderBy($sortBy, $sortOrder);

        return [$sortBy, $sortOrder];
    }
}
