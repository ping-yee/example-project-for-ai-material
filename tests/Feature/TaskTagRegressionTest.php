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

    public function test_list_search_and_general_filter_keep_tags_and_modes_do_not_intersect(): void
    {
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

        $this->getJson('/api/tasks')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.tags.0.id', $alpha->json('data.tags.0.id'));

        $search = $this->getJson('/api/tasks/search?query=mode&sort_by=title&sort_order=asc');
        $search
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonCount(1, 'data.0.tags');
        $this->assertSame(
            ['mode Alpha', 'mode Zulu'],
            collect($search->json('data'))->pluck('title')->all(),
        );

        $this->getJson('/api/tasks/filter?status=pending&priority=2&date_from=2026-08-15&date_to=2026-08-25')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $zulu->json('data.id'))
            ->assertJsonCount(1, 'data.0.tags');

        $this->getJson("/api/tasks/filter?tag={$tag->id}&status=in_progress&priority=1")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_complete_reopen_and_delete_preserve_tag_behavior_and_reusable_tags(): void
    {
        $tag = Tag::query()->create([
            'name' => '保留使用',
            'normalized_name' => '保留使用',
        ]);
        $created = $this->postJson('/api/tasks', [
            'title' => '既有操作任務',
            'tag_ids' => [$tag->id],
        ])->assertCreated();
        $taskId = $created->json('data.id');

        $this->putJson("/api/tasks/{$taskId}", ['status' => 'completed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.tags.0.id', $tag->id);

        $this->putJson("/api/tasks/{$taskId}", ['status' => 'pending'])
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.tags.0.id', $tag->id);

        $this->deleteJson("/api/tasks/{$taskId}")->assertOk();
        $this->getJson("/api/tasks/{$taskId}")->assertNotFound();
        $this->getJson('/api/tags')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $tag->id);
    }
}
