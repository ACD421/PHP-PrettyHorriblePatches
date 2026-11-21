<?php
$root = $argv[1] ?? (__DIR__ . DIRECTORY_SEPARATOR . 'work');
if (!is_dir($root) && !mkdir($root, 0777, true) && !is_dir($root)) {
    fwrite(STDERR, "mkdir failed\n");
    exit(2);
}
$root = rtrim(realpath($root) ?: $root, "\\/");
echo "PHP " . PHP_VERSION . " root=$root\n";

file_put_contents($root . DIRECTORY_SEPARATOR . 'real.php', 'REALBODY');

foreach (['real.php.', 'real.php..', 'real.php...'] as $name) {
    $path = $root . DIRECTORY_SEPARATOR . $name;
    $err = null;
    set_error_handler(function ($n, $s) use (&$err) { $err = $s; return true; });
    $data = file_get_contents($path);
    $rp = realpath($path);
    restore_error_handler();
    $shown = $data === false ? 'FAIL' : $data;
    echo "RESULT read[$name]=$shown realpath=" . ($rp === false ? 'false' : $rp) . "\n";
    if ($err) {
        echo "RESULT err[$name]=$err\n";
    }
}

$created = $root . DIRECTORY_SEPARATOR . 'created.php';
$alias = $root . DIRECTORY_SEPARATOR . 'created.php..';
@unlink($created);
$err = null;
set_error_handler(function ($n, $s) use (&$err) { $err = $s; return true; });
$n = file_put_contents($alias, 'CREATED');
restore_error_handler();
echo "RESULT write[created.php..]=" . ($n === false ? "FAIL:$err" : $n) . "\n";
echo "RESULT pathinfo_ext=" . pathinfo('created.php..', PATHINFO_EXTENSION) . "\n";
echo "RESULT exists[created.php]=" . (is_file($created) ? 'true' : 'false') . "\n";
echo "RESULT readback[created.php]=" . (file_get_contents($created) ?: 'FAIL') . "\n";
echo "DIRLIST\n";
foreach (scandir($root) ?: [] as $e) {
    if ($e === '.' || $e === '..') {
        continue;
    }
    echo "ENTRY $e\n";
}
echo "DONE exit=0\n";
