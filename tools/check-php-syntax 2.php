<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$directories = ['app', 'bootstrap', 'config', 'database', 'routes', 'tests', 'tools'];
$files = [];

foreach ($directories as $directory) {
    $path = $root.'/'.$directory;

    if (! is_dir($path)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
}

sort($files);
$failed = false;

foreach ($files as $file) {
    $output = [];
    $exitCode = 0;
    exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($file).' 2>&1', $output, $exitCode);

    $relativePath = substr($file, strlen($root) + 1);
    echo ($exitCode === 0 ? 'PASS' : 'FAIL')." | PHP syntax | {$relativePath}\n";

    if ($exitCode !== 0) {
        $failed = true;
        fwrite(STDERR, implode("\n", $output)."\n");
    }
}

echo 'RESULT | PHP syntax | '.count($files)." files checked\n";

exit($failed ? 1 : 0);
