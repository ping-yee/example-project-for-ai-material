<?php

declare(strict_types=1);

$input = json_decode(stream_get_contents(STDIN), true);
$input = is_array($input) ? $input : [];
$cwd = is_string($input['cwd'] ?? null) ? $input['cwd'] : (getcwd() ?: dirname(__DIR__));

$rootOutput = [];
$rootExit = 0;
exec('git -C '.escapeshellarg($cwd).' rev-parse --show-toplevel 2>&1', $rootOutput, $rootExit);
$root = $rootExit === 0 ? trim(implode("\n", $rootOutput)) : dirname(__DIR__);
$sessionKey = $input['session_id'] ?? $input['thread_id'] ?? $input['transcript_path'] ?? $root;
$stateFile = sys_get_temp_dir().'/ai-harness-stop-'.hash('sha256', (string) $sessionKey).'.json';
$maxRetries = max(1, (int) (getenv('HARNESS_MAX_RETRIES') ?: 3));
$state = [];

if (is_file($stateFile)) {
    $decoded = json_decode((string) file_get_contents($stateFile), true);
    $state = is_array($decoded) ? $decoded : [];
}

if (($state['allow_stop'] ?? false) === true) {
    @unlink($stateFile);
    echo json_encode([
        'continue' => false,
        'stopReason' => 'Harness retry limit reached. Review the reported blocker before continuing.',
    ], JSON_UNESCAPED_SLASHES)."\n";
    exit(0);
}

$output = [];
$exitCode = 0;
exec('cd '.escapeshellarg($root).' && composer verify 2>&1', $output, $exitCode);

if ($exitCode === 0) {
    @unlink($stateFile);
    echo "{}\n";
    exit(0);
}

$tail = implode("\n", array_slice($output, -100));
$normalizedTail = preg_replace(
    [
        '/\x1B(?:[@-Z\\-_]|\[[0-?]*[ -\/]*[@-~])/',
        '/\b\d+\.\d+s\b/',
    ],
    ['<ansi>', '<duration>'],
    $tail
);
$fingerprint = hash('sha256', is_string($normalizedTail) ? $normalizedTail : $tail);
$sameFailure = is_string($state['fingerprint'] ?? null)
    && hash_equals($state['fingerprint'], $fingerprint);
$attempt = $sameFailure ? ((int) ($state['attempt'] ?? 0)) + 1 : 1;

if ($attempt >= $maxRetries) {
    file_put_contents($stateFile, json_encode([
        'allow_stop' => true,
        'fingerprint' => $fingerprint,
    ]));
    $reason = "composer verify repeated the same failure for {$attempt}/{$maxRetries} stop attempts. "
        ."Do not modify more files. Report BLOCKED with the command output and the input needed from a human.\n\n{$tail}";
} else {
    file_put_contents($stateFile, json_encode([
        'attempt' => $attempt,
        'fingerprint' => $fingerprint,
    ]));
    $reason = "composer verify failed with the same root-cause signature at attempt {$attempt}/{$maxRetries}. "
        ."Fix only the smallest reported cause, rerun the same failing command, then rerun composer verify.\n\n{$tail}";
}

echo json_encode([
    'decision' => 'block',
    'reason' => $reason,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";
