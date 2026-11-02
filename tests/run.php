<?php
/**
 * Test runner: composer test (also run by the git pre-push hook).
 * Each file in tests/ named *Test.php returns an array of test name => closure.
 * A test fails by throwing; the runner exits non-zero if any test fails.
 */
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

define('ROOT', dirname(__DIR__));
require ROOT.'/frameworks/lib/Source.php';
require ROOT.'/frameworks/lib/FrameworkBuilder.php';
require __DIR__.'/lib/Assert.php';

$filter = $argv[1] ?? '';
$passed = 0;
$failed = array();

$load = function (string $file) {
    return require $file;
};

foreach (glob(__DIR__.'/*Test.php') as $file) {
    $tests = $load($file);

    foreach ($tests as $name => $test) {
        $label = basename($file, '.php').' > '.$name;

        if ($filter !== '' && stripos($label, $filter) === false) {
            continue;
        }

        try {
            $test();
            $passed++;
        } catch (Throwable $e) {
            $failed[] = $label."\n    ".$e->getMessage();
        }
    }
}

foreach ($failed as $failure) {
    echo "FAIL ".$failure."\n";
}

echo $passed.' passed, '.count($failed)." failed\n";
exit(count($failed) > 0 ? 1 : 0);
