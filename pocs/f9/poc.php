<?php
/**
 * proc_open NUL truncation.
 * fopen()/Z_PARAM_PATH reject the same strings. proc_open cwd, the string
 * command, and descriptorspec file paths are handed to C APIs via ZSTR_VAL,
 * so the kernel sees only the prefix before the first NUL.
 */
declare(strict_types=1);

$secret = '/tmp/proc-nul-secret';
$decoy  = '/tmp/proc-nul-decoy.txt';
$secdir = '/tmp/proc-nul-secretdir';
$pub    = '/tmp/proc-nul-public';
@mkdir($secdir, 0700, true);
@mkdir($pub, 0700, true);
file_put_contents($secret, "SECRET_FILE_CONTENTS\n");
file_put_contents($decoy, "DECOY\n");
file_put_contents($secdir . '/marker', "SECRET_DIR\n");

function show(string $label, string $value): void {
    echo $label . ' len=' . strlen($value) . ' hex=' . bin2hex($value) . "\n";
}

echo "PHP " . PHP_VERSION . "\n";

/* 1. Descriptor file path: suffix check passes, cat reads the secret. */
$file = $secret . "\0.txt";
show('file-arg', $file);
echo 'ends_with_.txt=' . (str_ends_with($file, '.txt') ? 'yes' : 'no') . "\n";
echo 'contains_NUL=' . (str_contains($file, "\0") ? 'yes' : 'no') . "\n";
try {
    $fh = fopen($file, 'r');
    echo "fopen=OPENED\n";
    if ($fh) fclose($fh);
} catch (Throwable $e) {
    echo 'fopen_rejected=' . $e->getMessage() . "\n";
}

$pipes = [];
$proc = proc_open(
    ['/bin/cat'],
    [
        0 => ['file', $file, 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ],
    $pipes
);
if (!is_resource($proc)) {
    echo "proc_open file failed\n";
} else {
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $rc = proc_close($proc);
    echo "file_rc=$rc\n";
    echo "file_stdout=" . json_encode($out) . "\n";
    echo "file_stderr=" . json_encode($err) . "\n";
}

/* 2. cwd: suffix /public passes, child pwd is the directory before NUL. */
$cwd = $secdir . "\0/public";
show('cwd-arg', $cwd);
echo 'ends_with_/public=' . (str_ends_with($cwd, '/public') ? 'yes' : 'no') . "\n";
$pipes = [];
$proc = proc_open(['/bin/pwd'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd);
if (!is_resource($proc)) {
    echo "proc_open cwd failed\n";
} else {
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $rc = proc_close($proc);
    echo "cwd_rc=$rc\n";
    echo "cwd_pwd=" . json_encode($out) . "\n";
    echo "cwd_err=" . json_encode($err) . "\n";
}

/* 3. String command accepts NUL; array form rejects it. Shell runs the prefix. */
$cmd = "id\0echo SAFE_ONLY";
show('cmd-arg', $cmd);
echo 'ends_with_SAFE_ONLY=' . (str_ends_with($cmd, 'echo SAFE_ONLY') ? 'yes' : 'no') . "\n";
echo 'has_metachar=' . (preg_match('/[;&|`$]/', $cmd) ? 'yes' : 'no') . "\n";
try {
    $pipes = [];
    proc_open([$cmd], [1 => ['pipe', 'w']], $pipes);
    echo "array_form=ACCEPTED\n";
} catch (Throwable $e) {
    echo 'array_form_rejected=' . $e->getMessage() . "\n";
}
$pipes = [];
$proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
if (!is_resource($proc)) {
    echo "proc_open cmd failed\n";
} else {
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $rc = proc_close($proc);
    echo "cmd_rc=$rc\n";
    echo "cmd_stdout=" . json_encode($out) . "\n";
    echo "cmd_stderr=" . json_encode($err) . "\n";
}

echo "DONE\n";
