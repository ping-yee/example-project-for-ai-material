<?php

declare(strict_types=1);

$ref = $argv[1] ?? 'WORKTREE';

function sourceAt(string $ref, string $path): string
{
    if ($ref === 'WORKTREE') {
        $content = file_get_contents(dirname(__DIR__).'/'.$path);
    } else {
        $command = 'git show '.escapeshellarg($ref.':'.$path);
        $content = shell_exec($command);
    }

    if ($content === false || $content === null || $content === '') {
        fwrite(STDERR, "無法讀取 {$ref}:{$path}\n");
        exit(2);
    }

    return $content;
}

function report(string $name, bool $passed): bool
{
    echo ($passed ? 'PASS' : 'FAIL')." | {$name}\n";

    return $passed;
}

$controller = sourceAt($ref, 'app/Http/Controllers/TaskController.php');
$routes = sourceAt($ref, 'routes/api.php');

$pdo = new PDO('sqlite::memory:');
$pdo->exec('CREATE TABLE tasks (title TEXT, due_date TEXT NULL)');
$pdo->exec("INSERT INTO tasks VALUES ('沒有日期', NULL), ('較晚', '2026-09-20'), ('較早', '2026-09-10')");

$hasNullLastClause = str_contains($controller, "orderByRaw('due_date IS NULL')");
$nullLast = $hasNullLastClause ? 'due_date IS NULL, ' : '';
$ascendingTitles = $pdo
    ->query("SELECT title FROM tasks ORDER BY {$nullLast}due_date ASC")
    ->fetchAll(PDO::FETCH_COLUMN);
$descendingTitles = $pdo
    ->query("SELECT title FROM tasks ORDER BY {$nullLast}due_date DESC")
    ->fetchAll(PDO::FETCH_COLUMN);

$resourcePosition = strpos($routes, "Route::apiResource('tasks'");
$filterPosition = strpos($routes, "Route::get('tasks/filter'");
$searchPosition = strpos($routes, "Route::get('tasks/search'");

$checks = [];
$checks[] = report('到期日升冪時 null 排最後', $ascendingTitles === ['較早', '較晚', '沒有日期']);
$checks[] = report('到期日降冪時 null 排最後', $descendingTitles === ['較晚', '較早', '沒有日期']);
$checks[] = report(
    '排序欄位與方向採嚴格白名單',
    str_contains($controller, 'in_array($sortBy, $allowedSortFields, true)')
        && str_contains($controller, "in_array(\$sortOrder, ['asc', 'desc'], true)")
);
$checks[] = report(
    'filter 固定路由位於資源參數路由之前',
    $filterPosition !== false && $resourcePosition !== false && $filterPosition < $resourcePosition
);
$checks[] = report(
    'search 固定路由位於資源參數路由之前',
    $searchPosition !== false && $resourcePosition !== false && $searchPosition < $resourcePosition
);

$passed = count(array_filter($checks));
$total = count($checks);
echo "RESULT | {$passed}/{$total} checks passed | ref={$ref}\n";

exit($passed === $total ? 0 : 1);
