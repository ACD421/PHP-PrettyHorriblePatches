<?php
$host = '127.0.0.1';
$port = 8080;

function http($host, $port, $req) {
    $errno = 0;
    $errstr = '';
    $fp = null;
    for ($i = 0; $i < 25; $i++) {
        $fp = @fsockopen($host, $port, $errno, $errstr, 1);
        if ($fp) {
            break;
        }
        usleep(200000);
    }
    if (!$fp) {
        fwrite(STDERR, "connect failed: $errno $errstr\n");
        exit(1);
    }
    stream_set_timeout($fp, 5);
    fwrite($fp, $req);
    $resp = stream_get_contents($fp);
    fclose($fp);
    return $resp;
}

function get($host, $port, $path, $cookie) {
    $h = "GET $path HTTP/1.1\r\nHost: $host:$port\r\nConnection: close\r\n";
    if ($cookie !== '') {
        $h .= "Cookie: $cookie\r\n";
    }
    $h .= "\r\n";
    return http($host, $port, $h);
}

function upload($host, $port, $cookie, $progress) {
    $boundary = '----wipeboundary';
    $body = "--{$boundary}\r\n"
        . "Content-Disposition: form-data; name=\"PHP_SESSION_UPLOAD_PROGRESS\"\r\n\r\n"
        . $progress . "\r\n"
        . "--{$boundary}\r\n"
        . "Content-Disposition: form-data; name=\"f\"; filename=\"a.txt\"\r\n"
        . "Content-Type: application/octet-stream\r\n\r\n"
        . "hello\r\n"
        . "--{$boundary}--\r\n";
    $req = "POST /wipe.php HTTP/1.1\r\n"
        . "Host: {$host}:{$port}\r\n"
        . "Cookie: {$cookie}\r\n"
        . "Content-Type: multipart/form-data; boundary={$boundary}\r\n"
        . "Content-Length: " . strlen($body) . "\r\n"
        . "Connection: close\r\n\r\n"
        . $body;
    return http($host, $port, $req);
}

function body($resp) {
    $p = strpos($resp, "\r\n\r\n");
    return $p === false ? $resp : substr($resp, $p + 4);
}

function sid_from($resp) {
    if (preg_match('/^Set-Cookie:\s*PHPSESSID=([^;\r\n]+)/mi', $resp, $m)) {
        return $m[1];
    }
    if (preg_match('/^ID:(.+)$/m', body($resp), $m)) {
        return trim($m[1]);
    }
    return '';
}

$r1 = get($host, $port, '/wipe.php?set=1', '');
echo "=== SET ===\n$r1\n";
$sid = sid_from($r1);
echo "PARSED_SID:$sid\n";
if ($sid === '' || !preg_match('/^[A-Za-z0-9,-]+$/', $sid)) {
    fwrite(STDERR, "no sid\n");
    exit(1);
}
$cookie = "PHPSESSID=$sid";

$r2 = upload($host, $port, $cookie, 'ok');
echo "=== UPLOAD ok ===\n$r2\n";

$r3 = get($host, $port, '/wipe.php', $cookie);
echo "=== AFTER ok ===\n$r3\n";

$r4 = upload($host, $port, $cookie, '|bad');
echo "=== UPLOAD pipe ===\n$r4\n";

$r5 = get($host, $port, '/wipe.php', $cookie);
echo "=== AFTER pipe ===\n$r5\n";

$b3 = body($r3);
$b5 = body($r5);
$kept = strpos($b3, 'SECRET:token') !== false;
$wiped = strpos($b5, 'SECRET:missing') !== false;
echo "CONTROL_KEPT:" . ($kept ? 'yes' : 'no') . "\n";
echo "PIPE_WIPED:" . ($wiped ? 'yes' : 'no') . "\n";
exit($kept && $wiped ? 0 : 1);
