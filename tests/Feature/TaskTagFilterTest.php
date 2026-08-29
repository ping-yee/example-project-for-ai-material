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

    /**
     * User Story: 作為使用者，我想以單一標籤、未加標籤或全部篩選任務，以便理解結果集合。
     *
     * Given: 同時具有標籤、單一標籤、未加標籤的任務及未使用標籤
     * When: 分別使用一般標籤、未加標籤、全部及未使用標籤篩選
     * Then: 回傳正確的多筆、單筆、完整及空結果，且保留完整標籤集合
     *
     * @test
     */
    public function test_when_filter_tasks_given_tag_modes_then_returns_expected_result_sets(): void
    {
        // Given: 同時具有標籤、單一標籤、未加標籤的任務及未使用標籤
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
        $this->postJson('/api/tasks', ['title' => '未加標籤'])->assertCreated();

        // When: 分別使用一般標籤、未加標籤、全部及未使用標籤篩選
        $alphaResult = $this->getJson("/api/tasks/filter?tag={$alpha->id}&sort_by=title&sort_order=asc");
        $untaggedResult = $this->getJson('/api/tasks/filter?tag=untagged');
        $allResult = $this->getJson('/api/tasks/filter?tag=all');
        $emptyResult = $this->getJson("/api/tasks/filter?tag={$unused->id}");

        // Then: 回傳正確的多筆、單筆、完整及空結果，且保留完整標籤集合
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
        $untaggedResult
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', '未加標籤')
            ->assertJsonPath('data.0.tags', []);
        $allResult->assertOk()->assertJsonCount(3, 'data');
        $emptyResult->assertOk()->assertJsonCount(0, 'data');
        $this->assertNotNull($both->json('data.id'));
    }

    /**
     * User Story: 作為使用者，我想讓標籤篩選沿用所有排序方式，以便延續整理偏好。
     *
     * Given: 兩項在六種排序欄位上具有明確順序的同標籤任務
     * When: 對每個欄位分別套用升序與降序標籤篩選
     * Then: 每種排序都回傳正確且互為反向的結果
     *
     * @test
     */
    public function test_when_filter_by_tag_given_each_sort_option_then_orders_both_directions(): void
    {
        // Given: 兩項在六種排序欄位上具有明確順序的同標籤任務
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

        // When: 對每個欄位分別套用升序與降序標籤篩選
        foreach (['created_at', 'updated_at', 'title', 'priority', 'status', 'due_date'] as $sortBy) {
            $ascending = $this->getJson(
                "/api/tasks/filter?tag={$tag->id}&sort_by={$sortBy}&sort_order=asc",
            )->assertOk();
            $descending = $this->getJson(
                "/api/tasks/filter?tag={$tag->id}&sort_by={$sortBy}&sort_order=desc",
            )->assertOk();

            // Then: 每種排序都回傳正確且互為反向的結果
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

    /**
     * User Story: 作為使用者，我想讓標籤篩選只接受既有標籤，以免無效條件看似成功。
     *
     * Given: 一個不存在的數字型標籤 ID
     * When: 嘗試以該 ID 篩選任務
     * Then: 回傳標籤欄位驗證錯誤而不是空的成功結果
     *
     * @test
     */
    public function test_when_filter_tasks_given_unknown_tag_id_then_returns_validation_error(): void
    {
        // Given: 一個不存在的數字型標籤 ID
        $unknownTagId = 999999;

        // When: 嘗試以該 ID 篩選任務
        $response = $this->getJson("/api/tasks/filter?tag={$unknownTagId}");

        // Then: 回傳標籤欄位驗證錯誤而不是空的成功結果
        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tag');
    }

    private function createTag(string $name): Tag
    {
        return Tag::query()->create([
            'name' => $name,
            'normalized_name' => Tag::normalizeName($name),
        ]);
    }
}
