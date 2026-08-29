<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskFilterSortingTest extends TestCase
{
    use RefreshDatabase;

    public function test_due_date_ascending_places_null_last(): void
    {
        $this->createSortingFixtures();

        $response = $this->getJson('/api/tasks/filter?sort_by=due_date&sort_order=asc');

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertSame(['較早', '較晚', '沒有日期'], $this->titles($response->json('data')));
    }

    public function test_due_date_descending_places_null_last(): void
    {
        $this->createSortingFixtures();

        $response = $this->getJson('/api/tasks/filter?sort_by=due_date&sort_order=desc');

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertSame(['較晚', '較早', '沒有日期'], $this->titles($response->json('data')));
    }

    public function test_invalid_sort_values_fall_back_safely(): void
    {
        $response = $this->getJson('/api/tasks/filter?sort_by=unsafe&sort_order=sideways');

        $response
            ->assertOk()
            ->assertJsonPath('filters.sort_by', 'created_at')
            ->assertJsonPath('filters.sort_order', 'desc');
    }

    public function test_filter_and_search_routes_are_not_captured_as_task_ids(): void
    {
        $this->getJson('/api/tasks/filter')->assertOk()->assertJsonStructure(['success', 'data', 'filters']);
        $this->getJson('/api/tasks/search?query=test')->assertOk()->assertJsonStructure(['success', 'data']);
    }

    private function createSortingFixtures(): void
    {
        Task::query()->create(['title' => '沒有日期', 'due_date' => null]);
        Task::query()->create(['title' => '較晚', 'due_date' => '2026-09-20']);
        Task::query()->create(['title' => '較早', 'due_date' => '2026-09-10']);
    }

    /**
     * @param  array<int, array<string, mixed>>  $tasks
     * @return list<string>
     */
    private function titles(array $tasks): array
    {
        return array_values(array_map(
            static fn (array $task): string => (string) $task['title'],
            $tasks
        ));
    }
}
