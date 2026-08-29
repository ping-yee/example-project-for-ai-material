<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class TaskMultipleTagsTest extends TestCase
{
    use RefreshDatabase;

    public function test_tag_options_are_sorted_searchable_and_include_unused_tags(): void
    {
        Tag::query()->create([
            'name' => 'Zulu',
            'normalized_name' => 'zulu',
        ]);
        Tag::query()->create([
            'name' => 'Alpha planning',
            'normalized_name' => 'alpha planning',
        ]);
        Tag::query()->create([
            'name' => 'Alpha review',
            'normalized_name' => 'alpha review',
        ]);

        $this->getJson('/api/tags')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.name', 'Alpha planning')
            ->assertJsonPath('data.1.name', 'Alpha review')
            ->assertJsonPath('data.2.name', 'Zulu')
            ->assertJsonMissing(['name' => '未加標籤']);

        $this->getJson('/api/tags?query=review')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Alpha review');
    }

    public function test_task_can_mix_existing_and_multiple_new_tags(): void
    {
        $existing = Tag::query()->create([
            'name' => '既有標籤',
            'normalized_name' => '既有標籤',
        ]);

        $created = $this->postJson('/api/tasks', [
            'title' => '混合標籤任務',
            'tag_ids' => [$existing->id],
            'new_tag_names' => ['新標籤 A', '新標籤 B'],
        ]);

        $created
            ->assertCreated()
            ->assertJsonPath('data.title', '混合標籤任務')
            ->assertJsonCount(3, 'data.tags');

        $this->assertSame(
            ['新標籤 A', '新標籤 B', '既有標籤'],
            collect($created->json('data.tags'))->pluck('name')->sort()->values()->all(),
        );

        $this->getJson('/api/tasks/'.$created->json('data.id'))
            ->assertOk()
            ->assertJsonCount(3, 'data.tags');
    }

    public function test_more_than_ten_combined_tags_rejects_the_whole_task(): void
    {
        $existingIds = collect(range(1, 6))->map(function (int $number): int {
            return Tag::query()->create([
                'name' => "既有 {$number}",
                'normalized_name' => "既有 {$number}",
            ])->id;
        })->all();

        $response = $this->postJson('/api/tasks', [
            'title' => '不可部分建立',
            'description' => '必須保留供使用者修正',
            'tag_ids' => $existingIds,
            'new_tag_names' => ['新 1', '新 2', '新 3', '新 4', '新 5'],
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tags');

        $this->getJson('/api/tasks')->assertJsonCount(0, 'data');
        $this->getJson('/api/tags')->assertJsonCount(6, 'data');
    }

    public function test_new_name_matching_an_existing_tag_is_rejected_without_auto_selecting_it(): void
    {
        $first = $this->postJson('/api/tasks', [
            'title' => '第一個任務',
            'new_tag_name' => 'Café 🚀',
        ])->assertCreated();

        $response = $this->postJson('/api/tasks', [
            'title' => '不可建立的第二個任務',
            'new_tag_names' => ["  CAFE\u{0301} 🚀  "],
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('new_tag_names.0');
        $this->assertSame(
            '標籤已存在，請從選項中選取',
            $response->json('errors')['new_tag_names.0'][0],
        );

        $this->getJson('/api/tasks')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $first->json('data.id'));
        $this->getJson('/api/tags')->assertJsonCount(1, 'data');
    }

    public function test_explicit_tag_updates_sync_the_collection_while_partial_updates_preserve_it(): void
    {
        $tags = collect(['A', 'B', 'C'])->mapWithKeys(function (string $name): array {
            $tag = Tag::query()->create([
                'name' => $name,
                'normalized_name' => strtolower($name),
            ]);

            return [$name => $tag->id];
        });

        $created = $this->postJson('/api/tasks', [
            'title' => '可編輯任務',
            'tag_ids' => [$tags['A'], $tags['B']],
        ])->assertCreated();
        $taskId = $created->json('data.id');

        $this->putJson("/api/tasks/{$taskId}", ['status' => 'completed'])
            ->assertOk()
            ->assertJsonCount(2, 'data.tags');

        $this->putJson("/api/tasks/{$taskId}", [
            'tag_ids' => [$tags['B'], $tags['C']],
            'new_tag_names' => ['D'],
        ])->assertOk()->assertJsonCount(3, 'data.tags');

        $cleared = $this->putJson("/api/tasks/{$taskId}", ['tag_ids' => []]);
        $cleared
            ->assertOk()
            ->assertJsonPath('data.tags', []);

        $this->getJson('/api/tags')->assertJsonCount(4, 'data');
    }

    public function test_tag_write_failure_rolls_back_task_update_and_relationship_sync(): void
    {
        $existing = Tag::query()->create([
            'name' => '保留標籤',
            'normalized_name' => '保留標籤',
        ]);
        $created = $this->postJson('/api/tasks', [
            'title' => '原始標題',
            'tag_ids' => [$existing->id],
        ])->assertCreated();
        $taskId = $created->json('data.id');

        DB::beforeExecuting(static function (string $query): void {
            if (preg_match('/insert into [`"]tags[`"]/i', $query) === 1) {
                throw new RuntimeException('Simulated tag write failure.');
            }
        });

        $this->putJson("/api/tasks/{$taskId}", [
            'title' => '不可留下的新標題',
            'tag_ids' => [],
            'new_tag_names' => ['不可留下的新標籤'],
        ])->assertServerError();

        $this->getJson("/api/tasks/{$taskId}")
            ->assertOk()
            ->assertJsonPath('data.title', '原始標題')
            ->assertJsonPath('data.tags.0.id', $existing->id);
    }
}
