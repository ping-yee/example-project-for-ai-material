<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tag;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PDOException;
use RuntimeException;
use Tests\TestCase;

class TaskMultipleTagsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * User Story: 作為使用者，我想搜尋穩定排序的全站標籤，以便重複使用標籤。
     *
     * Given: 全站有三個未使用標籤
     * When: 讀取完整與搜尋後的標籤選項
     * Then: 選項依名稱排序、搜尋正確且不包含虛構的未加標籤
     *
     * @test
     */
    public function test_when_list_tag_options_given_global_tags_then_returns_sorted_searchable_unused_tags(): void
    {
        // Given: 全站有三個未使用標籤
        foreach (['Zulu', 'Alpha planning', 'Alpha review'] as $name) {
            Tag::query()->create([
                'name' => $name,
                'normalized_name' => Tag::normalizeName($name),
            ]);
        }

        // When: 讀取完整與搜尋後的標籤選項
        $allTags = $this->getJson('/api/tags');
        $searchedTags = $this->getJson('/api/tags?query=review');

        // Then: 選項依名稱排序、搜尋正確且不包含虛構的未加標籤
        $allTags
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.name', 'Alpha planning')
            ->assertJsonPath('data.1.name', 'Alpha review')
            ->assertJsonPath('data.2.name', 'Zulu')
            ->assertJsonMissing(['name' => '未加標籤']);
        $searchedTags
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Alpha review');
    }

    /**
     * User Story: 作為使用者，我想混用既有與多個新標籤，以便完整整理任務。
     *
     * Given: 一個可重複使用的既有標籤
     * When: 以既有標籤 ID 與兩個新名稱建立任務
     * Then: 建立與後續讀取都回傳三個標籤
     *
     * @test
     */
    public function test_when_create_task_given_existing_and_new_tags_then_returns_complete_tag_collection(): void
    {
        // Given: 一個可重複使用的既有標籤
        $existing = Tag::query()->create([
            'name' => '既有標籤',
            'normalized_name' => '既有標籤',
        ]);

        // When: 以既有標籤 ID 與兩個新名稱建立任務
        $created = $this->postJson('/api/tasks', [
            'title' => '混合標籤任務',
            'tag_ids' => [$existing->id],
            'new_tag_names' => ['新標籤 A', '新標籤 B'],
        ]);

        // Then: 建立與後續讀取都回傳三個標籤
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

    /**
     * User Story: 作為使用者，我想讓一項任務最多有十個標籤，以便介面保持可讀。
     *
     * Given: 六個既有標籤與五個新標籤
     * When: 嘗試以合計十一個標籤建立任務
     * Then: 整筆任務與新標籤寫入失敗，既有標籤保留
     *
     * @test
     */
    public function test_when_create_task_given_more_than_ten_tags_then_rejects_entire_write(): void
    {
        // Given: 六個既有標籤與五個新標籤
        $existingIds = collect(range(1, 6))->map(function (int $number): int {
            return Tag::query()->create([
                'name' => "既有 {$number}",
                'normalized_name' => "既有 {$number}",
            ])->id;
        })->all();

        // When: 嘗試以合計十一個標籤建立任務
        $response = $this->postJson('/api/tasks', [
            'title' => '不可部分建立',
            'description' => '必須保留供使用者修正',
            'tag_ids' => $existingIds,
            'new_tag_names' => ['新 1', '新 2', '新 3', '新 4', '新 5'],
        ]);

        // Then: 整筆任務與新標籤寫入失敗，既有標籤保留
        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tags');
        $this->getJson('/api/tasks')->assertJsonCount(0, 'data');
        $this->getJson('/api/tags')->assertJsonCount(6, 'data');
    }

    /**
     * User Story: 作為使用者，我想在輸入同名新標籤時改選既有標籤，以免系統替我決定。
     *
     * Given: 已存在一個正規化名稱相同的標籤
     * When: 以大小寫及 Unicode 組合形式不同的名稱建立另一項任務
     * Then: 回傳指定欄位錯誤且不建立任務或重複標籤
     *
     * @test
     */
    public function test_when_create_task_given_existing_normalized_name_then_rejects_without_auto_select(): void
    {
        // Given: 已存在一個正規化名稱相同的標籤
        $first = $this->postJson('/api/tasks', [
            'title' => '第一個任務',
            'new_tag_name' => 'Café 🚀',
        ])->assertCreated();

        // When: 以大小寫及 Unicode 組合形式不同的名稱建立另一項任務
        $response = $this->postJson('/api/tasks', [
            'title' => '不可建立的第二個任務',
            'new_tag_names' => ["  CAFE\u{0301} 🚀  "],
        ]);

        // Then: 回傳指定欄位錯誤且不建立任務或重複標籤
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

    /**
     * User Story: 作為使用者，我想明確同步標籤集合並讓局部更新保留標籤，以免意外遺失關聯。
     *
     * Given: 一項具有 A、B 標籤的任務及可選的 C 標籤
     * When: 依序局部更新、同步 B/C/D，再明確清空集合
     * Then: 局部更新保留集合、同步取代集合且清空後標籤仍可重用
     *
     * @test
     */
    public function test_when_update_task_given_explicit_or_missing_tag_data_then_syncs_or_preserves_collection(): void
    {
        // Given: 一項具有 A、B 標籤的任務及可選的 C 標籤
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

        // When: 依序局部更新、同步 B/C/D，再明確清空集合
        $partial = $this->putJson("/api/tasks/{$taskId}", ['status' => 'completed']);
        $synced = $this->putJson("/api/tasks/{$taskId}", [
            'tag_ids' => [$tags['B'], $tags['C']],
            'new_tag_names' => ['D'],
        ]);
        $cleared = $this->putJson("/api/tasks/{$taskId}", ['tag_ids' => []]);

        // Then: 局部更新保留集合、同步取代集合且清空後標籤仍可重用
        $partial->assertOk()->assertJsonCount(2, 'data.tags');
        $synced->assertOk()->assertJsonCount(3, 'data.tags');
        $cleared
            ->assertOk()
            ->assertJsonPath('data.tags', []);
        $this->getJson('/api/tags')->assertJsonCount(4, 'data');
    }

    /**
     * User Story: 作為使用者，我想讓更新任務與標籤成為原子操作，以免資料不一致。
     *
     * Given: 一項具有既有標籤的任務且資料庫會拒絕新標籤
     * When: 同時更新標題與標籤集合
     * Then: 請求失敗且原始標題與標籤關聯保持不變
     *
     * @test
     */
    public function test_when_update_task_given_tag_write_failure_then_rolls_back_task_and_tags(): void
    {
        // Given: 一項具有既有標籤的任務且資料庫會拒絕新標籤
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

        // When: 同時更新標題與標籤集合
        $response = $this->putJson("/api/tasks/{$taskId}", [
            'title' => '不可留下的新標題',
            'tag_ids' => [],
            'new_tag_names' => ['不可留下的新標籤'],
        ]);

        // Then: 請求失敗且原始標題與標籤關聯保持不變
        $response->assertServerError();
        $this->getJson("/api/tasks/{$taskId}")
            ->assertOk()
            ->assertJsonPath('data.title', '原始標題')
            ->assertJsonPath('data.tags.0.id', $existing->id);
    }

    /**
     * User Story: 作為使用者，我想在複數標籤欄位格式錯誤時看見欄位錯誤，以便修正輸入。
     *
     * Given: 既有標籤與涵蓋陣列、型別、重複、存在性、空值及長度的無效 payload
     * When: 逐一嘗試建立任務
     * Then: 每個請求都回傳對應欄位錯誤且不建立任務
     *
     * @test
     */
    public function test_when_write_task_given_invalid_plural_tag_fields_then_returns_field_errors(): void
    {
        // Given: 既有標籤與涵蓋所有新規則的無效 payload
        $existing = Tag::query()->create([
            'name' => '既有',
            'normalized_name' => '既有',
        ]);
        $invalidCases = [
            [['tag_ids' => 'not-an-array'], 'tag_ids'],
            [['tag_ids' => ['not-an-id']], 'tag_ids.0'],
            [['tag_ids' => [$existing->id, $existing->id]], 'tag_ids.0'],
            [['tag_ids' => [999999]], 'tag_ids.0'],
            [['new_tag_names' => 'not-an-array'], 'new_tag_names'],
            [['new_tag_names' => ['']], 'new_tag_names.0'],
            [['new_tag_names' => [[]]], 'new_tag_names.0'],
            [['new_tag_names' => [str_repeat('長', 51)]], 'new_tag_names.0'],
        ];

        // When: 逐一嘗試建立任務
        $responses = collect($invalidCases)->map(function (array $case) {
            [$tagPayload, $errorField] = $case;

            return [$this->postJson('/api/tasks', ['title' => '不應建立'] + $tagPayload), $errorField];
        });

        // Then: 每個請求都回傳對應欄位錯誤且不建立任務
        $responses->each(function (array $result): void {
            [$response, $errorField] = $result;
            $response
                ->assertUnprocessable()
                ->assertJsonValidationErrors($errorField);
        });
        $this->getJson('/api/tasks')->assertJsonCount(0, 'data');
        $this->getJson('/api/tags')->assertJsonCount(1, 'data');
    }

    /**
     * User Story: 作為使用者，我想讓無效任務資料阻止新標籤建立，以免留下部分資料。
     *
     * Given: 空白任務標題與有效的新標籤名稱
     * When: 嘗試建立任務
     * Then: 任務、標籤與關聯都不會建立
     *
     * @test
     */
    public function test_when_create_task_given_invalid_task_with_new_tags_then_writes_nothing(): void
    {
        // Given: 空白任務標題與有效的新標籤名稱
        $payload = [
            'title' => '',
            'new_tag_names' => ['不應留下'],
        ];

        // When: 嘗試建立任務
        $response = $this->postJson('/api/tasks', $payload);

        // Then: 任務、標籤與關聯都不會建立
        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
        $this->getJson('/api/tasks')->assertJsonCount(0, 'data');
        $this->getJson('/api/tags')->assertJsonCount(0, 'data');
    }

    /**
     * User Story: 作為使用者，我想在併發建立同名標籤時收到欄位錯誤，以便改選既有標籤。
     *
     * Given: 唯一鍵在驗證後拒絕新標籤寫入
     * When: 建立帶有該新標籤的任務
     * Then: 回傳指定欄位 422 錯誤且任務交易回滾
     *
     * @test
     */
    public function test_when_create_task_given_concurrent_duplicate_tag_then_returns_field_validation_error(): void
    {
        // Given: 唯一鍵在驗證後拒絕新標籤寫入
        DB::beforeExecuting(static function (string $query): void {
            if (preg_match('/insert into [`"]tags[`"]/i', $query) === 1) {
                throw new UniqueConstraintViolationException(
                    'mysql_test',
                    $query,
                    [],
                    new PDOException('Duplicate normalized tag.', 23000),
                );
            }
        });

        // When: 建立帶有該新標籤的任務
        $response = $this->postJson('/api/tasks', [
            'title' => '競態任務',
            'new_tag_names' => ['競態標籤'],
        ]);

        // Then: 回傳指定欄位 422 錯誤且任務交易回滾
        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('new_tag_names.0');
        $this->assertSame(
            '標籤已存在，請從選項中選取',
            $response->json('errors')['new_tag_names.0'][0],
        );
        $this->getJson('/api/tasks')->assertJsonCount(0, 'data');
    }
}
