<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tag;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTagRegressionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * User Story: 作為使用者，我想在各種查詢模式中持續看見標籤且模式互斥，以便理解結果。
     *
     * Given: 兩項狀態、優先級與建立日期不同但具有同一標籤的任務
     * When: 使用完整清單、搜尋、一般組合篩選及帶殘留一般條件的標籤篩選
     * Then: 各模式保留標籤與排序，且標籤模式不形成隱藏交集
     *
     * @test
     */
    public function test_when_switch_query_modes_given_tagged_tasks_then_keeps_tags_and_prevents_intersections(): void
    {
        // Given: 兩項狀態、優先級與建立日期不同但具有同一標籤的任務
        $tag = Tag::query()->create([
            'name' => '模式標籤',
            'normalized_name' => '模式標籤',
        ]);

        $zulu = $this->postJson('/api/tasks', [
            'title' => 'mode Zulu',
            'status' => 'pending',
            'priority' => 2,
            'tag_ids' => [$tag->id],
        ])->assertCreated();
        $alpha = $this->postJson('/api/tasks', [
            'title' => 'mode Alpha',
            'status' => 'completed',
            'priority' => 3,
            'tag_ids' => [$tag->id],
        ])->assertCreated();

        Task::query()->whereKey($zulu->json('data.id'))->update(['created_at' => '2026-08-20 12:00:00']);
        Task::query()->whereKey($alpha->json('data.id'))->update(['created_at' => '2026-08-10 12:00:00']);

        // When: 使用完整清單、搜尋、一般組合篩選及帶殘留一般條件的標籤篩選
        $allTasks = $this->getJson('/api/tasks');
        $search = $this->getJson('/api/tasks/search?query=mode&sort_by=title&sort_order=asc');
        $generalFilter = $this->getJson(
            '/api/tasks/filter?status=pending&priority=2&date_from=2026-08-15&date_to=2026-08-25',
        );
        $tagFilter = $this->getJson("/api/tasks/filter?tag={$tag->id}&status=in_progress&priority=1");

        // Then: 各模式保留標籤與排序，且標籤模式不形成隱藏交集
        $allTasks
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.tags.0.id', $alpha->json('data.tags.0.id'));
        $search
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonCount(1, 'data.0.tags');
        $this->assertSame(
            ['mode Alpha', 'mode Zulu'],
            collect($search->json('data'))->pluck('title')->all(),
        );
        $generalFilter
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $zulu->json('data.id'))
            ->assertJsonCount(1, 'data.0.tags');
        $tagFilter
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    /**
     * User Story: 作為使用者，我想讓完成、重新開啟與刪除維持原行為，以免標籤功能造成回歸。
     *
     * Given: 一項具有可重複使用標籤的待處理任務
     * When: 依序完成、重新開啟並刪除任務
     * Then: 狀態操作保留標籤，刪除任務後標籤仍存在
     *
     * @test
     */
    public function test_when_complete_reopen_delete_given_tagged_task_then_preserves_behavior_and_reusable_tag(): void
    {
        // Given: 一項具有可重複使用標籤的待處理任務
        $tag = Tag::query()->create([
            'name' => '保留使用',
            'normalized_name' => '保留使用',
        ]);
        $created = $this->postJson('/api/tasks', [
            'title' => '既有操作任務',
            'tag_ids' => [$tag->id],
        ])->assertCreated();
        $taskId = $created->json('data.id');

        // When: 依序完成、重新開啟並刪除任務
        $completed = $this->putJson("/api/tasks/{$taskId}", ['status' => 'completed']);
        $reopened = $this->putJson("/api/tasks/{$taskId}", ['status' => 'pending']);
        $deleted = $this->deleteJson("/api/tasks/{$taskId}");

        // Then: 狀態操作保留標籤，刪除任務後標籤仍存在
        $completed
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.tags.0.id', $tag->id);
        $reopened
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.tags.0.id', $tag->id);
        $deleted->assertOk();
        $this->getJson("/api/tasks/{$taskId}")->assertNotFound();
        $this->getJson('/api/tags')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $tag->id);
    }
}
