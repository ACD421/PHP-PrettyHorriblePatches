<?php
/**
 * cli.prompt backtick code can ini_set() a new prompt. The next
 * cli_get_prompt() walks that buffer. A nested ini_restore('cli.prompt')
 * frees it, then the parser keeps reading the freed tail.
 */
$ini = __DIR__ . '/prompt.ini';
$cmd = [PHP_BINARY, '-n', '-c', $ini, '-a'];
$proc = proc_open($cmd, [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
], $pipes);
if (!is_resource($proc)) {
    fwrite(STDERR, "proc_open failed\n");
    exit(2);
}
fwrite($pipes[0], "x\n");
fclose($pipes[0]);
stream_set_blocking($pipes[1], false);
stream_set_blocking($pipes[2], false);
$out = '';
$err = '';
$st = null;
$deadline = microtime(true) + 20;
while (microtime(true) < $deadline) {
    $out .= (string) stream_get_contents($pipes[1]);
    $err .= (string) stream_get_contents($pipes[2]);
    $st = proc_get_status($proc);
    if (!$st['running']) {
        $out .= (string) stream_get_contents($pipes[1]);
        $err .= (string) stream_get_contents($pipes[2]);
        break;
    }
    usleep(20000);
}
if ($st && $st['running']) {
    proc_terminate($proc, 9);
    $st = proc_get_status($proc);
    echo "timeout\n";
}
echo "STDOUT=" . substr($out, 0, 400) . "\n";
echo "STDERR=" . substr($err, 0, 400) . "\n";
echo "exitcode=" . ($st['exitcode'] ?? '?') . " termsig=" . ($st['termsig'] ?? '?') . " stopsig=" . ($st['stopsig'] ?? '?') . "\n";
$closed = proc_close($proc);
echo "proc_close=$closed\n";
$sig = (int) ($st['termsig'] ?? 0);
if ($sig === 11 || $closed === 139) {
    exit(139);
}
exit($closed === 0 ? 0 : 1);
