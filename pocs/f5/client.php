<?php
function http_get(string $path, float $timeout = 8.0): array {
    $errno = 0;
    $errstr = '';
    $start = microtime(true);
    $fp = @fsockopen('127.0.0.1', 8080, $errno, $errstr, 3.0);
    if (!$fp) {
        return [
            'dt' => microtime(true) - $start,
            'body' => "CONNECT_FAIL errno=$errno err=$errstr",
            'raw' => '',
        ];
    }
    stream_set_timeout($fp, (int) ceil($timeout));
    fwrite($fp, "GET $path HTTP/1.0\r\nHost: 127.0.0.1\r\nConnection: close\r\n\r\n");
    $raw = stream_get_contents($fp);
    $meta = stream_get_meta_data($fp);
    fclose($fp);
    $body = $raw;
    $pos = strpos($raw, "\r\n\r\n");
    if ($pos !== false) {
        $body = substr($raw, $pos + 4);
    }
    if (!empty($meta['timed_out'])) {
        $body .= "\nCLIENT_STREAM_TIMEOUT\n";
    }
    return [
        'dt' => microtime(true) - $start,
        'body' => $body,
        'raw' => $raw,
    ];
}

function server_pids(): array {
    $pids = [];
    foreach (glob('/proc/[0-9]*/cmdline') ?: [] as $cmdf) {
        $cmd = @file_get_contents($cmdf);
        if ($cmd === false || !str_contains($cmd, '127.0.0.1:8080')) {
            continue;
        }
        $pid = (int) basename(dirname($cmdf));
        if ($pid > 1) {
            $pids[] = $pid;
        }
    }
    return $pids;
}

function restart_server(string $extraArgs): void {
    foreach (server_pids() as $pid) {
        shell_exec('kill -9 ' . $pid . ' 2>/dev/null');
    }
    usleep(300000);
    $cmd = 'php ' . $extraArgs . ' -d display_errors=1 -d html_errors=0 -S 127.0.0.1:8080 -t LABROOT/findings/bailout >/tmp/srv.log 2>&1 & echo $!';
    $pid = trim((string) shell_exec($cmd));
    echo "RESTARTED pid=$pid args=$extraArgs\n";
    echo 'LISTEN=' . implode(',', server_pids()) . "\n";
    usleep(400000);
}

echo "=== CANARY BAILOUTS (default server) ===\n";
foreach (['timeout_autoload', 'compile_bailout', 'output_handler'] as $op) {
    echo "----- REQUEST op=$op -----\n";
    $r = http_get('/index.php?op=' . $op, 8.0);
    echo 'DT=' . round($r['dt'], 3) . "\n";
    echo $r['body'] . "\n";
    echo "----- PROBE after $op -----\n";
    $p = http_get('/index.php?op=probe', 5.0);
    echo 'DT=' . round($p['dt'], 3) . "\n";
    echo $p['body'] . "\n";
}

echo "=== TIMER CONTROL server max_execution_time=0 max_input_time=-1 ===\n";
restart_server('-d max_execution_time=0 -d max_input_time=-1 -d hard_timeout=2');
$c = http_get('/check.php?tag=control', 8.0);
echo 'DT=' . round($c['dt'], 3) . "\n";
echo $c['body'] . "\n";

echo "=== TIMER ATTACK server max_execution_time=0 max_input_time=1 ===\n";
restart_server('-d max_execution_time=0 -d max_input_time=1 -d hard_timeout=2');
$a = http_get('/arm.php', 5.0);
echo "----- ARM -----\n";
echo 'DT=' . round($a['dt'], 3) . "\n";
echo $a['body'] . "\n";
$after = http_get('/check.php?tag=after_arm', 8.0);
echo "----- CHECK after arm -----\n";
echo 'DT=' . round($after['dt'], 3) . "\n";
echo $after['body'] . "\n";

echo "----- re-arm then check with set_time_limit(0) -----\n";
$a2 = http_get('/arm.php', 5.0);
echo 'ARM2 DT=' . round($a2['dt'], 3) . "\n";
echo $a2['body'] . "\n";
$cleared = http_get('/check.php?tag=cleared&clear=1', 8.0);
echo 'CLEARED DT=' . round($cleared['dt'], 3) . "\n";
echo $cleared['body'] . "\n";

echo "----- lone long request on attack server (no prior arm in this beat) -----\n";
restart_server('-d max_execution_time=0 -d max_input_time=1 -d hard_timeout=2');
$lone = http_get('/check.php?tag=lone', 8.0);
echo 'LONE DT=' . round($lone['dt'], 3) . "\n";
echo $lone['body'] . "\n";

echo "=== SRV LOG ===\n";
echo shell_exec('cat /tmp/srv.log 2>/dev/null') ?: "(no srv log)\n";
echo "CLIENT_DONE\n";
