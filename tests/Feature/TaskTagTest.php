<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class TaskTagTest extends TestCase
{
    use RefreshDatabase;

    /**
     * User Story: 作為使用者，我想在建立任務時新增標籤，以便辨識任務。
     *
     * Given: 有效的任務資料與包含頭尾空白的標籤名稱
     * When: 建立任務後透過單筆與清單介面讀取
     * Then: 三個回應使用相同的完整標籤形狀，且名稱已移除頭尾空白
     *
     * @test
     */
    public function test_when_create_task_given_valid_new_tag_then_tag_shape_is_consistent(): void
    {
        // Given: 有效的任務與標籤資料
        $payload = [
            'title' => '準備發表',
            'new_tag_name' => '  重要 🚀  ',
        ];

        // When: 建立任務
        $created = $this->postJson('/api/tasks', $payload);

        // Then: 建立、單筆與清單回應具有一致的標籤資料
        $created
            ->assertCreated()
            ->assertJsonPath('data.title', '準備發表')
            ->assertJsonPath('data.tags.0.name', '重要 🚀');

        $taskId = $created->json('data.id');
        $expectedTags = $created->json('data.tags');

        $this->assertSame([
            ['id' => $expectedTags[0]['id'], 'name' => '重要 🚀'],
        ], $expectedTags);

        $this->getJson("/api/tasks/{$taskId}")
            ->assertOk()
            ->assertJsonPath('data.tags', $expectedTags);

        $this->getJson('/api/tasks')
            ->assertOk()
            ->assertJsonPath('data.0.tags', $expectedTags);
    }

    /**
     * User Story: 作為使用者，我想建立未加標籤的任務，以便稍後再整理。
     *
     * Given: 未提供標籤的有效任務資料
     * When: 建立並讀取任務
     * Then: 回應以空集合表示未加標籤
     *
     * @test
     */
    public function test_when_create_task_given_no_tag_then_returns_empty_tag_collection(): void
    {
        // Given: 未提供標籤的任務資料
        $payload = ['title' => '沒有標籤的任務'];

        // When: 建立任務
        $created = $this->postJson('/api/tasks', $payload);

        // Then: 建立與讀取回應都包含空的標籤集合
        $created
            ->assertCreated()
            ->assertJsonPath('data.tags', []);

        $this->getJson("/api/tasks/{$created->json('data.id')}")
            ->assertOk()
            ->assertJsonPath('data.tags', []);
    }

    /**
     * User Story: 作為使用者，我想在標籤名稱無效時看見欄位錯誤，以便修正輸入。
     *
     * Given: 只有空白的標籤名稱
     * When: 嘗試建立任務
     * Then: 回傳欄位驗證錯誤且任務清單保持空白
     *
     * @test
     */
    public function test_when_create_task_given_blank_tag_name_then_validation_fails_without_task(): void
    {
        // Given: 只有空白的標籤名稱
        $payload = [
            'title' => '不應建立',
            'new_tag_name' => '   ',
        ];

        // When: 嘗試建立任務
        $response = $this->postJson('/api/tasks', $payload);

        // Then: 回傳欄位錯誤，且沒有留下任務
        $response->assertUnprocessable()->assertJsonValidationErrors('new_tag_name');
        $this->getJson('/api/tasks')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /**
     * User Story: 作為使用者，我想在標籤名稱不是文字時看見欄位錯誤，以便修正輸入。
     *
     * Given: 陣列型態的標籤名稱
     * When: 嘗試建立任務
     * Then: 回傳標籤名稱欄位驗證錯誤
     *
     * @test
     */
    public function test_when_create_task_given_non_string_tag_name_then_validation_fails(): void
    {
        // Given: 非字串的標籤名稱
        $payload = [
            'title' => '不應建立',
            'new_tag_name' => ['錯誤型態'],
        ];

        // When: 嘗試建立任務
        $response = $this->postJson('/api/tasks', $payload);

        // Then: 回傳欄位驗證錯誤
        $response->assertUnprocessable()->assertJsonValidationErrors('new_tag_name');
    }

    /**
     * User Story: 作為使用者，我想以可理解字元計算標籤長度，以便正確使用 emoji。
     *
     * Given: 分別包含 50 與 51 個組合 emoji 的標籤名稱
     * When: 建立兩個任務
     * Then: 50 個字元成功，51 個字元得到欄位驗證錯誤
     *
     * @test
     */
    public function test_when_create_task_given_grapheme_boundaries_then_enforces_fifty_character_limit(): void
    {
        // Given: 由多個 code point 組成的一個家庭 emoji
        $familyEmoji = '👨‍👩‍👧‍👦';

        // When: 分別使用 50 與 51 個可理解字元建立任務
        $atLimit = $this->postJson('/api/tasks', [
            'title' => '五十個字元',
            'new_tag_name' => str_repeat($familyEmoji, 50),
        ]);
        $overLimit = $this->postJson('/api/tasks', [
            'title' => '五十一個字元',
            'new_tag_name' => str_repeat($familyEmoji, 51),
        ]);

        // Then: 邊界值成功，超出邊界則失敗且沒有留下第二個任務
        $atLimit->assertCreated();
        $overLimit->assertUnprocessable()->assertJsonValidationErrors('new_tag_name');

        $this->getJson('/api/tasks')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    /**
     * User Story: 作為使用者，我想重複使用不分大小寫的同名標籤，以免產生重複標籤。
     *
     * Given: 兩個只在大小寫與 Unicode 組合形式不同的標籤名稱
     * When: 建立兩個任務
     * Then: 兩個任務回傳相同標籤識別碼與首次建立的顯示名稱
     *
     * @test
     */
    public function test_when_create_tasks_given_case_and_unicode_variants_then_reuses_one_tag(): void
    {
        // Given: 兩個只在大小寫與 Unicode 組合形式不同的標籤名稱
        $firstPayload = ['title' => '第一個任務', 'new_tag_name' => 'Café 🚀'];
        $secondPayload = ['title' => '第二個任務', 'new_tag_name' => "CAFE\u{0301} 🚀"];

        // When: 建立兩個任務
        $first = $this->postJson('/api/tasks', $firstPayload)->assertCreated();
        $second = $this->postJson('/api/tasks', $secondPayload)->assertCreated();

        // Then: 回應指向同一個標籤
        $this->assertSame(
            $first->json('data.tags.0.id'),
            $second->json('data.tags.0.id'),
        );
        $second->assertJsonPath('data.tags.0.name', 'Café 🚀');
    }

    /**
     * User Story: 作為使用者，我想保留正規化後仍不同的標籤，以免錯誤合併名稱。
     *
     * Given: 只在重音符號不同的兩個標籤名稱
     * When: 建立兩個任務
     * Then: 兩個任務回傳不同的標籤識別碼
     *
     * @test
     */
    public function test_when_create_tasks_given_distinct_normalized_names_then_keeps_tags_separate(): void
    {
        // Given: 正規化後仍不相同的兩個名稱
        $plainPayload = ['title' => '無重音任務', 'new_tag_name' => 'cafe'];
        $accentedPayload = ['title' => '有重音任務', 'new_tag_name' => 'café'];

        // When: 建立兩個任務
        $plain = $this->postJson('/api/tasks', $plainPayload)->assertCreated();
        $accented = $this->postJson('/api/tasks', $accentedPayload)->assertCreated();

        // Then: 回應指向不同標籤
        $this->assertNotSame(
            $plain->json('data.tags.0.id'),
            $accented->json('data.tags.0.id'),
        );
        $accented->assertJsonPath('data.tags.0.name', 'café');
    }

    /**
     * User Story: 作為使用者，我想在搜尋或篩選後仍看見任務標籤，以便保持辨識脈絡。
     *
     * Given: 一個具有標籤的待處理任務
     * When: 分別透過搜尋與篩選介面讀取任務
     * Then: 兩個清單回應都包含相同的完整標籤集合
     *
     * @test
     */
    public function test_when_read_task_lists_given_search_and_filter_then_tags_remain_consistent(): void
    {
        // Given: 一個具有標籤的待處理任務
        $created = $this->postJson('/api/tasks', [
            'title' => 'searchable-task',
            'new_tag_name' => '可搜尋',
        ])->assertCreated();
        $expectedTags = $created->json('data.tags');

        // When: 分別透過搜尋與篩選介面讀取任務
        $searched = $this->getJson('/api/tasks/search?query=searchable');
        $filtered = $this->getJson('/api/tasks/filter?status=pending');

        // Then: 兩個回應都保留相同的標籤集合
        $searched->assertOk()->assertJsonPath('data.0.tags', $expectedTags);
        $filtered->assertOk()->assertJsonPath('data.0.tags', $expectedTags);
    }

    /**
     * User Story: 作為使用者，我不希望標籤寫入失敗時留下不完整任務，以免資料不一致。
     *
     * Given: 資料庫拒絕建立標籤
     * When: 嘗試建立有標籤的任務
     * Then: 請求失敗且透過任務清單讀不到部分寫入的任務
     *
     * @test
     */
    public function test_when_create_task_given_tag_write_failure_then_rolls_back_task(): void
    {
        // Given: 在資料庫邊界注入標籤寫入失敗
        DB::beforeExecuting(static function (string $query): void {
            if (preg_match('/insert into [`"]tags[`"]/i', $query) === 1) {
                throw new RuntimeException('Simulated tag write failure.');
            }
        });

        // When: 嘗試建立有標籤的任務
        $response = $this->postJson('/api/tasks', [
            'title' => '不可留下的任務',
            'new_tag_name' => '失敗標籤',
        ]);

        // Then: 請求失敗，且外部任務清單沒有部分資料
        $response->assertServerError();
        $this->getJson('/api/tasks')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
