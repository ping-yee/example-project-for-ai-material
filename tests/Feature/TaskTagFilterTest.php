<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tag;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTagFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_filter_returns_one_tag_untagged_all_and_empty_result_sets(): void
    {
        $alpha = $this->createTag('Alpha');
        $beta = $this->createTag('Beta');
        $unused = $this->createTag('Unused');

        $both = $this->postJson('/api/tasks', [
            'title' => 'B both tags',
            'tag_ids' => [$alpha->id, $beta->id],
        ])->assertCreated();
        $this->postJson('/api/tasks', [
            'title' => 'A alpha only',
            'tag_ids' => [$alpha->id],
        ])->assertCreated();
        $this->postJson('/api/tasks', [
            'title' => '未加標籤',
        ])->assertCreated();

        $alphaResult = $this->getJson("/api/tasks/filter?tag={$alpha->id}&sort_by=title&sort_order=asc");
        $alphaResult
            ->assertOk()
            ->assertJsonPath('filters.tag', (string) $alpha->id)
            ->assertJsonCount(2, 'data');
        $bothResult = collect($alphaResult->json('data'))->firstWhere('title', 'B both tags');
        $this->assertCount(2, $bothResult['tags']);
        $this->assertSame(
            ['A alpha only', 'B both tags'],
            collect($alphaResult->json('data'))->pluck('title')->all(),
        );

        $this->getJson('/api/tasks/filter?tag=untagged')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', '未加標籤')
            ->assertJsonPath('data.0.tags', []);

        $this->getJson('/api/tasks/filter?tag=all')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $this->getJson("/api/tasks/filter?tag={$unused->id}")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->assertNotNull($both->json('data.id'));
    }

    public function test_tag_filter_supports_every_existing_sort_field_in_both_directions(): void
    {
        $tag = $this->createTag('Sortable');
        $first = $this->postJson('/api/tasks', [
            'title' => 'Alpha',
            'priority' => 1,
            'status' => 'completed',
            'due_date' => '2026-09-01',
            'tag_ids' => [$tag->id],
        ])->assertCreated();
        $second = $this->postJson('/api/tasks', [
            'title' => 'Zulu',
            'priority' => 3,
            'status' => 'pending',
            'due_date' => '2026-09-30',
            'tag_ids' => [$tag->id],
        ])->assertCreated();

        Task::query()->whereKey($first->json('data.id'))->update([
            'created_at' => '2026-08-01 00:00:00',
            'updated_at' => '2026-08-02 00:00:00',
        ]);
        Task::query()->whereKey($second->json('data.id'))->update([
            'created_at' => '2026-08-10 00:00:00',
            'updated_at' => '2026-08-11 00:00:00',
        ]);

        foreach (['created_at', 'updated_at', 'title', 'priority', 'status', 'due_date'] as $sortBy) {
            $ascending = $this->getJson(
                "/api/tasks/filter?tag={$tag->id}&sort_by={$sortBy}&sort_order=asc",
            )->assertOk();
            $descending = $this->getJson(
                "/api/tasks/filter?tag={$tag->id}&sort_by={$sortBy}&sort_order=desc",
            )->assertOk();

            $expectedAscending = $sortBy === 'status'
                ? ['Zulu', 'Alpha']
                : ['Alpha', 'Zulu'];
            $this->assertSame(
                $expectedAscending,
                collect($ascending->json('data'))->pluck('title')->all(),
                "Ascending {$sortBy} order was incorrect.",
            );
            $this->assertSame(
                array_reverse($expectedAscending),
                collect($descending->json('data'))->pluck('title')->all(),
                "Descending {$sortBy} order was incorrect.",
            );
        }
    }

    private function createTag(string $name): Tag
    {
        return Tag::query()->create([
            'name' => $name,
            'normalized_name' => Tag::normalizeName($name),
        ]);
    }
}
